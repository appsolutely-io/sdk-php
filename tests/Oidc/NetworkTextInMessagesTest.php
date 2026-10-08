<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Oidc;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Exception\IdTokenException;
use Appsolutely\Sdk\Exception\TransportException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use Appsolutely\Sdk\Tests\Support\SigningKey;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Text that arrived over the network (an endpoint the discovery document
 * names, the message of the HTTP client's exception) reaches an exception
 * message or a log entry only as short printable text, so it can neither
 * forge a log line nor flood one.
 */
final class NetworkTextInMessagesTest extends TestCase
{
    public function testTheHttpClientsErrorReachesTheMessageOnlyAsShortPrintableText(): void
    {
        $provider = new FakeProvider();
        $failing = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ("connection reset\r\nFORGED log line \e[31m" . str_repeat('x', 300)) extends RuntimeException implements ClientExceptionInterface {};
            }
        };
        $config = $provider->config();
        $oidc = (new Client(new Config(
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $failing,
            requestFactory: $config->requestFactory,
            streamFactory: $config->streamFactory,
            cache: $config->cache,
            clock: $config->clock,
        )))->oidc();

        try {
            $oidc->metadata();
            self::fail('A failed request was not reported.');
        } catch (TransportException $exception) {
            self::assertStringContainsString('connection reset??FORGED log line ?[31m', $exception->getMessage());
            self::assertDoesNotMatchRegularExpression('/[^\x20-\x7E]/', $exception->getMessage());
            self::assertStringNotContainsString(str_repeat('x', 250), $exception->getMessage());
            self::assertInstanceOf(ClientExceptionInterface::class, $exception->getPrevious());
        }
    }

    public function testADiscoveredTokenEndpointReachesTheMessageOnlyAsShortText(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['token_endpoint'] = FakeProvider::ISSUER . '/oauth/' . str_repeat('x', 300);
        $provider->token = fn(): ResponseInterface => $provider->factory->createResponse(502);

        try {
            $provider->oidc()->machineToken();
            self::fail('A failed token request was not reported.');
        } catch (UnexpectedResponseException $exception) {
            self::assertStringContainsString('502', $exception->getMessage());
            self::assertStringNotContainsString(str_repeat('x', 250), $exception->getMessage());
        }
    }

    public function testADiscoveredKeySetUrlReachesTheMessageOnlyAsShortText(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['jwks_uri'] = FakeProvider::ISSUER . '/oauth/' . str_repeat('x', 300);
        $provider->jwksStatus = 503;

        try {
            $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');
            self::fail('An unreachable key set was not reported.');
        } catch (DiscoveryException $exception) {
            self::assertStringContainsString('503', $exception->getMessage());
            self::assertStringNotContainsString(str_repeat('x', 250), $exception->getMessage());
        }
    }

    public function testADiscoveredKeySetUrlReachesTheLogOnlyAsShortText(): void
    {
        $provider = new FakeProvider();
        $provider->discovery['jwks_uri'] = FakeProvider::ISSUER . '/oauth/' . str_repeat('x', 300);
        $logger = new RecordingLogger();
        $provider->oidc()->verifyIdToken($provider->rsa->sign($provider->claims()), 'the-nonce');
        $provider->clock->advance(61);

        try {
            $provider->oidc(logger: $logger)->verifyIdToken(SigningKey::rsa('unpublished')->sign($provider->claims()), 'the-nonce');
            self::fail('An unknown key was accepted.');
        } catch (IdTokenException) {
        }

        self::assertCount(1, $logger->records);
        $uri = $logger->records[0]['context']['jwks_uri'] ?? null;
        self::assertIsString($uri);
        self::assertStringNotContainsString(str_repeat('x', 250), $uri);
    }
}

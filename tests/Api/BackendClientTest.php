<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Api;

use Appsolutely\Sdk\Api\BackendToken;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\OAuthException;
use Appsolutely\Sdk\Exception\UnauthenticatedException;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The site's backend calls with a token its own machine client is granted
 * (`client_credentials`), asked for when first needed, kept in the cache
 * until shortly before it expires, and asked for again once when the site
 * no longer accepts it.
 */
final class BackendClientTest extends TestCase
{
    private const string TOKEN_URL = FakeProvider::ISSUER . '/oauth/token';

    private FakeProvider $provider;

    private int $issued = 0;

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
        $this->provider->token = fn(RequestInterface $request): ResponseInterface => $this->provider->json([
            'access_token' => 'machine-' . ++$this->issued,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
        $this->provider->site = fn(): ResponseInterface => $this->provider->json(['client_id' => FakeProvider::BACKEND_CLIENT_ID]);
    }

    public function testACallCarriesATokenTheBackendCredentialIsGranted(): void
    {
        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');

        $tokenRequests = $this->provider->requestsTo('POST', self::TOKEN_URL);
        self::assertCount(1, $tokenRequests);
        self::assertSame(['grant_type' => 'client_credentials'], FakeProvider::form($tokenRequests[0]));
        self::assertSame(
            'Basic ' . base64_encode(urlencode(FakeProvider::BACKEND_CLIENT_ID) . ':' . urlencode(FakeProvider::BACKEND_CLIENT_SECRET)),
            $tokenRequests[0]->getHeaderLine('Authorization'),
        );
        self::assertSame('Bearer machine-1', $this->provider->siteRequests()[0]->getHeaderLine('Authorization'));
    }

    public function testTheScopesNamedAreAskedFor(): void
    {
        $this->provider->backendClient(['seats:read', 'roles:read'])->backend()->raw()->get('/api/v1/client');

        self::assertSame(['grant_type' => 'client_credentials', 'scope' => 'seats:read roles:read'], FakeProvider::form($this->provider->requestsTo('POST', self::TOKEN_URL)[0]));
    }

    public function testPostingTheCredentialSendsItInTheBody(): void
    {
        $this->provider->backendClient([], ClientAuthentication::ClientSecretPost)->backend()->raw()->get('/api/v1/client');

        $request = $this->provider->requestsTo('POST', self::TOKEN_URL)[0];
        self::assertSame(
            ['grant_type' => 'client_credentials', 'client_id' => FakeProvider::BACKEND_CLIENT_ID, 'client_secret' => FakeProvider::BACKEND_CLIENT_SECRET],
            FakeProvider::form($request),
        );
        self::assertSame('', $request->getHeaderLine('Authorization'));
    }

    public function testTheBackendAuthenticatesItsOwnWayWhenTold(): void
    {
        $client = new Client(new Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $this->provider->http,
            requestFactory: $this->provider->factory,
            streamFactory: $this->provider->factory,
            cache: $this->provider->cache,
            clock: $this->provider->clock,
            clientAuthentication: ClientAuthentication::ClientSecretBasic,
            backendClientId: FakeProvider::BACKEND_CLIENT_ID,
            backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET,
            backendClientAuthentication: ClientAuthentication::ClientSecretPost,
        ));

        $client->backend()->raw()->get('/api/v1/client');

        $request = $this->provider->requestsTo('POST', self::TOKEN_URL)[0];
        self::assertSame(FakeProvider::BACKEND_CLIENT_SECRET, FakeProvider::form($request)['client_secret'] ?? null);
        self::assertSame('', $request->getHeaderLine('Authorization'));
    }

    public function testATokenAnsweredWithoutALifetimeIsKeptForTheDefaultOne(): void
    {
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['access_token' => 'machine-' . ++$this->issued, 'token_type' => 'Bearer']);
        $backend = $this->provider->backendClient()->backend()->raw();

        $backend->get('/api/v1/client');
        $this->provider->clock->advance(BackendToken::DEFAULT_LIFETIME - BackendToken::EXPIRY_MARGIN - BackendToken::REFRESH_WINDOW - 1);
        $backend->get('/api/v1/client');
        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));

        $this->provider->clock->advance(BackendToken::REFRESH_WINDOW + 1);
        $backend->get('/api/v1/client');
        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    /**
     * Kept until its refresh window, replaced at a random point within it
     * (see BackendTokenTest), and never used past its margin.
     */
    public function testATokenIsKeptUntilShortlyBeforeItExpires(): void
    {
        $backend = $this->provider->backendClient()->backend()->raw();

        $backend->get('/api/v1/client');
        $this->provider->clock->advance(3600 - BackendToken::EXPIRY_MARGIN - BackendToken::REFRESH_WINDOW - 1);
        $backend->get('/api/v1/client');
        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));

        $this->provider->clock->advance(BackendToken::REFRESH_WINDOW + 1);
        $backend->get('/api/v1/client');
        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame('Bearer machine-2', $this->provider->siteRequests()[2]->getHeaderLine('Authorization'));
    }

    /**
     * Under PHP-FPM every request builds its own Client: the token lives in
     * the cache they share, not in the object.
     */
    public function testAnotherClientOnTheSameCacheReusesTheToken(): void
    {
        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');

        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame('Bearer machine-1', $this->provider->siteRequests()[1]->getHeaderLine('Authorization'));
    }

    public function testAnotherScopeIsAnotherToken(): void
    {
        $this->provider->backendClient(['seats:read'])->backend()->raw()->get('/api/v1/client');
        $this->provider->backendClient(['roles:read'])->backend()->raw()->get('/api/v1/client');

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    public function testATokenTheSiteNoLongerAcceptsIsReplacedOnce(): void
    {
        $answers = [$this->provider->problem(401, 'invalid-token'), $this->provider->json(['client_id' => FakeProvider::BACKEND_CLIENT_ID])];
        $this->provider->site = static function () use (&$answers): ResponseInterface {
            $answer = array_shift($answers);
            self::assertNotNull($answer);

            return $answer;
        };

        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame(['Bearer machine-1', 'Bearer machine-2'], array_map(static fn(RequestInterface $request): string => $request->getHeaderLine('Authorization'), $this->provider->siteRequests()));
    }

    public function testATokenRefusedAgainIsTheSitesAnswer(): void
    {
        $this->provider->site = fn(): ResponseInterface => $this->provider->problem(401, 'invalid-token');

        try {
            $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
            self::fail('A second refusal of a fresh token is the site\'s answer.');
        } catch (UnauthenticatedException) {
        }

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertCount(2, $this->provider->siteRequests());
    }

    public function testARefusalForAnotherReasonIsNotRetriedWithANewToken(): void
    {
        $this->provider->site = fn(): ResponseInterface => $this->provider->problem(401, 'unauthenticated');

        try {
            $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
            self::fail('A refusal that is not of the token is the site\'s answer.');
        } catch (UnauthenticatedException) {
        }

        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertCount(1, $this->provider->siteRequests());
    }

    public function testATokenEndpointRefusalIsAnOAuthErrorThatDoesNotNameTheSecret(): void
    {
        $this->provider->token = fn(): ResponseInterface => $this->provider->json(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401);

        try {
            $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
            self::fail('A refused credential is an OAuth error.');
        } catch (OAuthException $exception) {
            self::assertSame('invalid_client', $exception->error);
            self::assertStringNotContainsString(FakeProvider::BACKEND_CLIENT_SECRET, $exception->getMessage());
        }

        self::assertSame([], $this->provider->siteRequests());
    }

    public function testABackendIdWithoutASecretIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->config(backendClientId: FakeProvider::BACKEND_CLIENT_ID);
    }

    public function testABackendSecretWithoutAnIdIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->config(backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET);
    }

    public function testAScopeWithASpaceIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->config(backendClientId: FakeProvider::BACKEND_CLIENT_ID, backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET, backendScopes: ['seats:read roles:read']);
    }

    public function testTheBackendIsRefusedWhenTheConfigHoldsNoCredentialForIt(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('backend');

        (new Client($this->config()))->backend();
    }

    public function testADumpedConfigDoesNotShowTheBackendSecret(): void
    {
        $config = $this->config(backendClientId: FakeProvider::BACKEND_CLIENT_ID, backendClientSecret: FakeProvider::BACKEND_CLIENT_SECRET);

        self::assertStringNotContainsString(FakeProvider::BACKEND_CLIENT_SECRET, print_r($config, true));
        self::assertStringContainsString(FakeProvider::BACKEND_CLIENT_ID, print_r($config, true));
    }

    /**
     * @param list<string> $backendScopes
     */
    private function config(?string $backendClientId = null, ?string $backendClientSecret = null, array $backendScopes = []): Config
    {
        return new Config(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: FakeProvider::CLIENT_ID,
            clientSecret: FakeProvider::CLIENT_SECRET,
            httpClient: $this->provider->http,
            requestFactory: $this->provider->factory,
            streamFactory: $this->provider->factory,
            backendClientId: $backendClientId,
            backendClientSecret: $backendClientSecret,
            backendScopes: $backendScopes,
        );
    }
}

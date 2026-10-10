<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Cache\InMemoryCache;
use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Oidc\ClientAuthentication;
use Appsolutely\Sdk\Oidc\OpenIdClient;
use Http\Message\RequestMatcher\CallbackRequestMatcher;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * An in-memory stand-in for a site: its OpenID provider, shaped like the
 * site's discovery document, key set and token endpoint, and its API, whose
 * answers a test supplies through $site.
 */
final class FakeProvider
{
    public const string BASE_URL = 'https://site.example.com';
    public const string ISSUER = 'https://login.example.com';
    public const string CLIENT_ID = 'client-123';
    public const string CLIENT_SECRET = 's3cret/with+chars';
    public const string API_TOKEN = '7|admin-token-value';

    public readonly FrozenClock $clock;
    public readonly RecordingSleeper $sleeper;
    public readonly InMemoryCache $cache;
    public readonly MockClient $http;
    public readonly Psr17Factory $factory;
    public readonly SigningKey $rsa;
    public readonly SigningKey $ec;

    /** @var array<string, mixed> */
    public array $discovery;

    /** @var array<string, string> */
    public array $discoveryHeaders = ['Cache-Control' => 'public, max-age=600'];

    /** @var list<array<string, string>> */
    public array $jwks;

    /** @var array<string, string> */
    public array $jwksHeaders = ['Cache-Control' => 'public, max-age=3600'];

    public int $jwksStatus = 200;

    /** @var (callable(RequestInterface): ResponseInterface)|null */
    public $token = null;

    /** @var (callable(RequestInterface): ResponseInterface)|null */
    public $userInfo = null;

    /** @var (callable(RequestInterface): ResponseInterface)|null */
    public $revoke = null;

    /**
     * Answers every request under BASE_URL.
     *
     * @var (callable(RequestInterface): ResponseInterface)|null
     */
    public $site = null;

    public function __construct(?FrozenClock $clock = null)
    {
        $this->clock = $clock ?? new FrozenClock();
        $this->cache = new InMemoryCache($this->clock);
        $this->sleeper = new RecordingSleeper($this->clock);
        $this->factory = new Psr17Factory();
        $this->http = new MockClient($this->factory);
        $this->rsa = SigningKey::rsa('rsa-1');
        $this->ec = SigningKey::ec('ec-1');
        $this->jwks = [$this->rsa->jwk, $this->ec->jwk];
        $this->discovery = [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER . '/oauth/authorize',
            'token_endpoint' => self::ISSUER . '/oauth/token',
            'userinfo_endpoint' => self::ISSUER . '/oauth/userinfo',
            'jwks_uri' => self::ISSUER . '/oauth/jwks.json',
            'revocation_endpoint' => self::ISSUER . '/oauth/revoke',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256', 'ES256'],
            'authorization_response_iss_parameter_supported' => true,
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic', 'none'],
            'code_challenge_methods_supported' => ['S256'],
        ];
        $this->http->on(new CallbackRequestMatcher(static fn(): bool => true), fn(RequestInterface $request): ResponseInterface => $this->handle($request));
    }

    public function config(ClientAuthentication $authentication = ClientAuthentication::ClientSecretBasic, int $leeway = 60, ?LoggerInterface $logger = null): Config
    {
        return new Config(
            baseUrl: self::BASE_URL,
            issuer: self::ISSUER,
            clientId: self::CLIENT_ID,
            clientSecret: self::CLIENT_SECRET,
            apiToken: self::API_TOKEN,
            httpClient: $this->http,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            cache: $this->cache,
            clock: $this->clock,
            logger: $logger,
            clientAuthentication: $authentication,
            clockLeeway: $leeway,
            retryPolicy: new RetryPolicy(sleeper: $this->sleeper),
        );
    }

    public function client(?string $apiToken = self::API_TOKEN, ?RetryPolicy $retryPolicy = null, ?LoggerInterface $logger = null): Client
    {
        return new Client(new Config(
            baseUrl: self::BASE_URL,
            issuer: self::ISSUER,
            clientId: self::CLIENT_ID,
            clientSecret: self::CLIENT_SECRET,
            apiToken: $apiToken,
            httpClient: $this->http,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            cache: $this->cache,
            clock: $this->clock,
            logger: $logger,
            retryPolicy: $retryPolicy ?? new RetryPolicy(sleeper: $this->sleeper),
        ));
    }

    public function oidc(ClientAuthentication $authentication = ClientAuthentication::ClientSecretBasic, int $leeway = 60, ?LoggerInterface $logger = null): OpenIdClient
    {
        return (new Client($this->config($authentication, $leeway, $logger)))->oidc();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => self::ISSUER,
            'sub' => 'member-42',
            'aud' => self::CLIENT_ID,
            'exp' => $this->clock->timestamp() + 300,
            'iat' => $this->clock->timestamp(),
            'nonce' => 'the-nonce',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function json(array $body, int $status = 200, array $headers = []): ResponseInterface
    {
        $response = $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * An RFC 9457 refusal shaped as the site sends it.
     *
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     */
    public function problem(int $status, string $slug, array $extensions = [], array $headers = []): ResponseInterface
    {
        $response = $this->json(['type' => 'https://appsolutely.io/problems/' . $slug, 'title' => $slug, 'status' => $status, ...$extensions], $status, $headers);

        return $response->withHeader('Content-Type', 'application/problem+json');
    }

    /**
     * The requests that reached the site's API, in order.
     *
     * @return list<RequestInterface>
     */
    public function siteRequests(): array
    {
        return array_values(array_filter(
            $this->http->getRequests(),
            static fn(RequestInterface $request): bool => str_starts_with((string) $request->getUri(), self::BASE_URL . '/'),
        ));
    }

    /**
     * @return list<RequestInterface>
     */
    public function requestsTo(string $method, string $url): array
    {
        return array_values(array_filter(
            $this->http->getRequests(),
            static fn(RequestInterface $request): bool => $request->getMethod() === $method && (string) $request->getUri() === $url,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function form(RequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $fields);

        $form = [];
        foreach ($fields as $name => $value) {
            if (is_string($value)) {
                $form[(string) $name] = $value;
            }
        }

        return $form;
    }

    private function handle(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $method = $request->getMethod();

        if ($method === 'GET' && $url === self::ISSUER . '/.well-known/openid-configuration') {
            return $this->json($this->discovery, 200, $this->discoveryHeaders);
        }
        if ($method === 'GET' && $url === $this->discovery['jwks_uri']) {
            return $this->json(['keys' => $this->jwks], $this->jwksStatus, $this->jwksHeaders);
        }
        if ($method === 'POST' && $url === $this->discovery['token_endpoint'] && $this->token !== null) {
            return ($this->token)($request);
        }
        if ($method === 'GET' && $url === self::ISSUER . '/oauth/userinfo' && $this->userInfo !== null) {
            return ($this->userInfo)($request);
        }
        if ($method === 'POST' && $url === self::ISSUER . '/oauth/revoke' && $this->revoke !== null) {
            return ($this->revoke)($request);
        }
        if (str_starts_with($url, self::BASE_URL . '/') && $this->site !== null) {
            return ($this->site)($request);
        }

        return $this->json(['error' => 'not_found'], 404);
    }
}

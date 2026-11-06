<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Admin;

use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminConfig;
use Appsolutely\Sdk\Api\RetryPolicy;
use Appsolutely\Sdk\Exception\ForbiddenException;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Exception\UnauthenticatedException;
use Appsolutely\Sdk\Tests\Support\FakeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class AdminClientTest extends TestCase
{
    private const string ADMIN_CLIENT_ID = 'admin-credential-789';

    private const string ADMIN_CLIENT_SECRET = 'adm1n/s3cret+chars';

    private const string TOKEN_URL = FakeProvider::ISSUER . '/oauth/token';

    private FakeProvider $provider;

    /** @var list<string> */
    private array $issued = [];

    protected function setUp(): void
    {
        $this->provider = new FakeProvider();
        $this->provider->token = function (RequestInterface $request): ResponseInterface {
            $token = 'admin-access-' . (count($this->issued) + 1);
            $this->issued[] = $token;

            return $this->provider->json(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 600]);
        };
        $this->provider->site = fn(): ResponseInterface => $this->provider->json(['data' => [], 'next_cursor' => null]);
    }

    public function testACallCarriesTheTokenTheCredentialIsGrantedForItsScopes(): void
    {
        $this->admin(['admin:articles:read', 'admin:orders:read'])->raw()->get('/api/admin/v1/articles');

        $tokenRequests = $this->provider->requestsTo('POST', self::TOKEN_URL);
        self::assertCount(1, $tokenRequests);
        self::assertSame(['grant_type' => 'client_credentials', 'scope' => 'admin:articles:read admin:orders:read'], FakeProvider::form($tokenRequests[0]));
        self::assertSame('Basic ' . base64_encode(urlencode(self::ADMIN_CLIENT_ID) . ':' . urlencode(self::ADMIN_CLIENT_SECRET)), $tokenRequests[0]->getHeaderLine('Authorization'));

        $site = $this->provider->siteRequests();
        self::assertCount(1, $site);
        self::assertSame(FakeProvider::BASE_URL . '/api/admin/v1/articles', (string) $site[0]->getUri());
        self::assertSame('Bearer admin-access-1', $site[0]->getHeaderLine('Authorization'));
    }

    public function testTheTokenIsKeptBetweenCallsAndApartFromTheBackendsToken(): void
    {
        $this->provider->backendClient()->backend()->raw()->get('/api/v1/client');
        $admin = $this->admin(['admin:articles:read']);
        $admin->raw()->get('/api/admin/v1/articles');
        $admin->raw()->get('/api/admin/v1/articles');

        // One token for the backend, one for the administrator credential, none asked twice.
        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertSame('Bearer admin-access-2', $this->provider->siteRequests()[2]->getHeaderLine('Authorization'));
    }

    public function testATokenTheSiteNoLongerAcceptsIsAskedForAgainOnce(): void
    {
        $answers = [
            $this->provider->problem(401, 'invalid-token'),
            $this->provider->json(['data' => []]),
        ];
        $this->provider->site = function () use (&$answers): ResponseInterface {
            return array_shift($answers) ?? $this->provider->problem(500, 'server-error');
        };

        $this->admin(['admin:articles:read'])->raw()->get('/api/admin/v1/articles');

        self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        $site = $this->provider->siteRequests();
        self::assertSame('Bearer admin-access-1', $site[0]->getHeaderLine('Authorization'));
        self::assertSame('Bearer admin-access-2', $site[1]->getHeaderLine('Authorization'));
    }

    public function testATokenRefusedTwiceIsThrown(): void
    {
        $this->provider->site = fn(): ResponseInterface => $this->provider->problem(401, 'invalid-token');

        $this->expectException(UnauthenticatedException::class);

        try {
            $this->admin(['admin:articles:read'])->raw()->get('/api/admin/v1/articles');
        } finally {
            self::assertCount(2, $this->provider->requestsTo('POST', self::TOKEN_URL));
        }
    }

    /**
     * A scope the credential lacks is not a token problem: asking for a new
     * token, or sending the call again, would be refused the same way.
     */
    public function testAMissingScopeIsForbiddenWithoutRenewingOrRetrying(): void
    {
        $this->provider->site = fn(): ResponseInterface => $this->provider->problem(403, 'insufficient-scope', ['required' => 'admin:orders:read', 'granted' => ['admin:articles:read']]);

        try {
            $this->admin(['admin:articles:read'])->raw()->get('/api/admin/v1/orders');
            self::fail('The refusal was not thrown.');
        } catch (ForbiddenException $exception) {
            self::assertTrue($exception->hasType('insufficient-scope'));
            self::assertSame('admin:orders:read', $exception->extension('required'));
        }

        self::assertCount(1, $this->provider->requestsTo('POST', self::TOKEN_URL));
        self::assertCount(1, $this->provider->siteRequests());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsOutsideTheAdministratorApi(): iterable
    {
        yield 'the Site API' => ['/api/v1/me'];
        yield 'the version root alone' => ['/api/admin/v1'];
        yield 'a lookalike version' => ['/api/admin/v10/articles'];
        yield 'a lookalike prefix' => ['/api/admin/v1x/articles'];
        yield 'climbing out of it' => ['/api/admin/v1/../../v1/me'];
        yield 'the token endpoint' => ['/oauth/token'];
        yield 'an encoded slash' => ['/api/admin/v1/articles%2f..%2f..%2fv1%2fme'];
        yield 'an encoded backslash' => ['/api/admin/v1/articles%5c..%5cme'];
        yield 'a dot segment with a parameter' => ['/api/admin/v1/..;/v1/me'];
    }

    #[DataProvider('pathsOutsideTheAdministratorApi')]
    public function testAPathOutsideTheAdministratorApiIsRefusedBeforeAnythingIsSent(string $path): void
    {
        try {
            $this->admin(['admin:articles:read'])->raw()->get($path);
            self::fail('The path was sent.');
        } catch (InvalidArgumentValueException $exception) {
            self::assertStringContainsString('/api/admin/v1/', $exception->getMessage());
        }

        self::assertSame([], $this->provider->siteRequests());
        self::assertSame([], $this->provider->requestsTo('POST', self::TOKEN_URL));
    }

    public function testTheConfigNeverShowsTheSecretAndIsNotSerialized(): void
    {
        $config = $this->config(['admin:articles:read']);

        self::assertStringNotContainsString(self::ADMIN_CLIENT_SECRET, print_r($config, true));
        self::assertStringNotContainsString(self::ADMIN_CLIENT_SECRET, print_r(new AdminClient($config), true));

        self::assertStringNotContainsString(self::ADMIN_CLIENT_SECRET, var_export((array) $config, true));
        self::assertSame(self::ADMIN_CLIENT_SECRET, $config->clientSecret());

        $this->expectException(NotSerializableException::class);
        serialize($config);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function scopesThatAreNotAdministratorScopes(): iterable
    {
        yield 'none' => [[]];
        yield 'a member ability' => [['me:read']];
        yield 'a backend scope' => [['catalogue:read']];
        yield 'an OpenID scope' => [['openid']];
        yield 'a malformed one' => [['admin:articles']];
        yield 'one with a space' => [['admin:articles:read admin:orders:read']];
    }

    /**
     * @param list<string> $scopes
     */
    #[DataProvider('scopesThatAreNotAdministratorScopes')]
    public function testOnlyAdministratorScopesAreAskedFor(array $scopes): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->config($scopes);
    }

    public function testAnEmptyClientIdOrSecretIsRefused(): void
    {
        foreach ([['', self::ADMIN_CLIENT_SECRET], [self::ADMIN_CLIENT_ID, '']] as [$id, $secret]) {
            try {
                new AdminConfig(baseUrl: FakeProvider::BASE_URL, issuer: FakeProvider::ISSUER, clientId: $id, clientSecret: $secret, scopes: ['admin:articles:read']);
                self::fail('An empty client id or secret was accepted.');
            } catch (InvalidConfigException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @param list<string> $scopes
     */
    private function admin(array $scopes): AdminClient
    {
        return new AdminClient($this->config($scopes));
    }

    /**
     * @param list<string> $scopes
     */
    private function config(array $scopes): AdminConfig
    {
        return new AdminConfig(
            baseUrl: FakeProvider::BASE_URL,
            issuer: FakeProvider::ISSUER,
            clientId: self::ADMIN_CLIENT_ID,
            clientSecret: self::ADMIN_CLIENT_SECRET,
            scopes: $scopes,
            httpClient: $this->provider->http,
            requestFactory: $this->provider->factory,
            streamFactory: $this->provider->factory,
            cache: $this->provider->cache,
            clock: $this->provider->clock,
            retryPolicy: new RetryPolicy(sleeper: $this->provider->sleeper),
        );
    }
}

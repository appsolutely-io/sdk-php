<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Admin\AdminClient;
use Appsolutely\Sdk\Admin\AdminConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The Administrator API document pinned beside the Site API's, at the same
 * commit: AdminClient sends every call under the path the document serves,
 * asks for its token where the document says, and accepts every scope the
 * document grants.
 */
final class AdminContractTest extends TestCase
{
    private const string DOCUMENT = __DIR__ . '/admin-openapi.yaml';

    public function testCallsAreSentUnderThePathTheDocumentServes(): void
    {
        $servers = self::document()['servers'] ?? null;

        self::assertIsArray($servers);
        self::assertSame([['url' => rtrim(AdminClient::BASE_PATH, '/')]], $servers);
    }

    public function testTheCredentialIsAClientCredentialsClientOfTheIssuer(): void
    {
        $flows = self::at(self::document(), 'components', 'securitySchemes', 'administratorCredential', 'flows');

        self::assertSame(['clientCredentials'], array_keys($flows));
        self::assertSame('/oauth/token', self::at($flows, 'clientCredentials')['tokenUrl'] ?? null);
    }

    public function testEveryScopeTheDocumentGrantsCanBeAskedFor(): void
    {
        $scopes = array_keys(self::at(self::document(), 'components', 'securitySchemes', 'administratorCredential', 'flows', 'clientCredentials', 'scopes'));

        self::assertNotSame([], $scopes);

        $config = new AdminConfig(baseUrl: 'https://site.example.com', issuer: 'https://login.example.com', clientId: 'admin-credential', clientSecret: 'secret', scopes: array_map('strval', $scopes));

        self::assertSame(array_map('strval', $scopes), $config->scopes);
    }

    /**
     * The object at a path of keys, asserted to be one at every step.
     *
     * @param array<array-key, mixed> $node
     * @return array<array-key, mixed>
     */
    private static function at(array $node, string ...$keys): array
    {
        foreach ($keys as $key) {
            $node = $node[$key] ?? null;
            self::assertIsArray($node, $key);
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(): array
    {
        $document = Yaml::parseFile(self::DOCUMENT);
        self::assertIsArray($document);

        /** @var array<string, mixed> $document */
        return $document;
    }
}

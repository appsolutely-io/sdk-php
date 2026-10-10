<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Contract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * `composer pin-contract <checkout> <ref>` copies the site's API document
 * from a checkout of the site software at one commit and records that
 * commit in Contract::REVISION, in one run, so the copy the contract test
 * reads and the revision the package reports cannot name different commits.
 */
final class PinContractTest extends TestCase
{
    private const string DOCUMENT = "openapi: 3.1.0\ninfo:\n  title: Site API\n  version: v1\npaths: {}\n";

    private string $scratch;

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/sdk-pin-contract-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/site/docs/api', 0o777, true);
        mkdir($this->scratch . '/package/src', 0o777, true);
        mkdir($this->scratch . '/package/tests/Contract', 0o777, true);
        copy(dirname(__DIR__, 2) . '/src/Contract.php', $this->scratch . '/package/src/Contract.php');
    }

    protected function tearDown(): void
    {
        self::remove($this->scratch);
    }

    public function testThePinnedDocumentIsAnOpenApiDocumentAtACommit(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/D', Contract::REVISION);

        $document = Yaml::parseFile(dirname(__DIR__) . '/Contract/openapi.yaml');
        self::assertIsArray($document);
        self::assertSame('3.1.0', $document['openapi'] ?? null);
    }

    public function testItCopiesTheDocumentAndRecordsTheCommitTheRefNames(): void
    {
        $first = $this->commitDocument(self::DOCUMENT);
        $this->git('tag', 'v0.19.0');
        $this->commitDocument(str_replace('v1', 'v1-changed', self::DOCUMENT));

        [$exitCode, $output] = $this->pin('v0.19.0');

        self::assertSame(0, $exitCode, $output);
        self::assertSame(self::DOCUMENT, file_get_contents($this->scratch . '/package/tests/Contract/openapi.yaml'));
        self::assertStringContainsString("public const string REVISION = '" . $first . "';", (string) file_get_contents($this->scratch . '/package/src/Contract.php'));
        self::assertStringContainsString($first, $output);
    }

    public function testARefWithoutTheDocumentChangesNothing(): void
    {
        file_put_contents($this->scratch . '/site/README.md', "site\n");
        $this->git('init', '--quiet', '--initial-branch=main');
        $this->git('add', 'README.md');
        $this->git('-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'init');
        $before = (string) file_get_contents($this->scratch . '/package/src/Contract.php');

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('docs/api/openapi.yaml', $output);
        self::assertSame($before, file_get_contents($this->scratch . '/package/src/Contract.php'));
        self::assertFileDoesNotExist($this->scratch . '/package/tests/Contract/openapi.yaml');
    }

    public function testARefThatNamesNoCommitIsRefused(): void
    {
        $this->commitDocument(self::DOCUMENT);

        [$exitCode, $output] = $this->pin('no-such-ref');

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('no-such-ref', $output);
        self::assertFileDoesNotExist($this->scratch . '/package/tests/Contract/openapi.yaml');
    }

    public function testWhenTheRevisionCannotBeWrittenTheDocumentIsNotPinnedEither(): void
    {
        $this->commitDocument(self::DOCUMENT);
        $before = (string) file_get_contents($this->scratch . '/package/src/Contract.php');
        // A directory where the revision's temporary file would go makes
        // that write fail whoever runs the suite.
        mkdir($this->scratch . '/package/src/Contract.php.pinning');

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode, $output);
        self::assertFileDoesNotExist($this->scratch . '/package/tests/Contract/openapi.yaml');
        self::assertFileDoesNotExist($this->scratch . '/package/tests/Contract/openapi.yaml.pinning');
        self::assertSame($before, file_get_contents($this->scratch . '/package/src/Contract.php'));
    }

    public function testTheUsageNamesEveryOption(): void
    {
        [, $output] = $this->execute([PHP_BINARY, dirname(__DIR__, 2) . '/tools/pin-contract.php']);

        self::assertStringContainsString('--root=', $output);
    }

    public function testBothArgumentsAreRequired(): void
    {
        [$exitCode, $output] = $this->execute([PHP_BINARY, dirname(__DIR__, 2) . '/tools/pin-contract.php', '--root=' . $this->scratch . '/package', $this->scratch . '/site']);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('Usage', $output);
    }

    private function commitDocument(string $contents): string
    {
        if (!is_dir($this->scratch . '/site/.git')) {
            $this->git('init', '--quiet', '--initial-branch=main');
        }
        file_put_contents($this->scratch . '/site/docs/api/openapi.yaml', $contents);
        $this->git('add', 'docs/api/openapi.yaml');
        $this->git('-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'document');

        return trim($this->git('rev-parse', 'HEAD'));
    }

    /**
     * @return array{int, string}
     */
    private function pin(string $ref): array
    {
        return $this->execute([PHP_BINARY, dirname(__DIR__, 2) . '/tools/pin-contract.php', '--root=' . $this->scratch . '/package', $this->scratch . '/site', $ref]);
    }

    private function git(string ...$arguments): string
    {
        [$exitCode, $output] = $this->execute(['git', '-C', $this->scratch . '/site', ...array_values($arguments)]);
        self::assertSame(0, $exitCode, $output);

        return $output;
    }

    /**
     * Runs a command without the GIT_* variables a git hook running this
     * suite would pass down, which would point git at the package's own
     * repository instead of the scratch one.
     *
     * @param list<string> $command
     * @return array{int, string}
     */
    private function execute(array $command): array
    {
        $environment = [];
        foreach (getenv() as $name => $value) {
            if (!str_starts_with($name, 'GIT_')) {
                $environment[$name] = $value;
            }
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $entries = scandir($path);
            foreach ($entries === false ? [] : $entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}

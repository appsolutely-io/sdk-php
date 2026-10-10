<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Contract;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** Where the tool, the revision and the copy of the document sit in a package, and the document in the site software. */
    private const string TOOL = 'tools/pin-contract.php';
    private const string CONTRACT_FILE = 'src/Contract.php';
    private const string DOCUMENT_COPY = 'tests/Contract/openapi.yaml';
    private const string SITE_DOCUMENT = 'docs/api/openapi.yaml';
    /** What a run leaves beside a file it writes: the new contents, and the original until both are in place. */
    private const string PINNING = '.pinning';
    private const string UNPINNED = '.unpinned';

    private string $scratch;

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/sdk-pin-contract-' . bin2hex(random_bytes(6));
        mkdir(dirname($this->site(self::SITE_DOCUMENT)), 0o777, true);
        mkdir(dirname($this->package(self::CONTRACT_FILE)), 0o777, true);
        mkdir(dirname($this->package(self::DOCUMENT_COPY)), 0o777, true);
        copy(self::own(self::CONTRACT_FILE), $this->package(self::CONTRACT_FILE));
    }

    protected function tearDown(): void
    {
        self::remove($this->scratch);
    }

    public function testThePinnedDocumentIsAnOpenApiDocumentAtACommit(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/D', Contract::REVISION);

        $document = Yaml::parseFile(self::own(self::DOCUMENT_COPY));
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
        self::assertSame(self::DOCUMENT, file_get_contents($this->package(self::DOCUMENT_COPY)));
        self::assertStringContainsString("public const string REVISION = '" . $first . "';", (string) file_get_contents($this->package(self::CONTRACT_FILE)));
        self::assertStringContainsString($first, $output);
    }

    public function testARefWithoutTheDocumentChangesNothing(): void
    {
        file_put_contents($this->site('README.md'), "site\n");
        $this->git('init', '--quiet', '--initial-branch=main');
        $this->git('add', 'README.md');
        $this->git('-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'init');
        $before = (string) file_get_contents($this->package(self::CONTRACT_FILE));

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString(self::SITE_DOCUMENT, $output);
        self::assertSame($before, file_get_contents($this->package(self::CONTRACT_FILE)));
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY));
    }

    public function testARefThatNamesNoCommitIsRefused(): void
    {
        $this->commitDocument(self::DOCUMENT);

        [$exitCode, $output] = $this->pin('no-such-ref');

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('no-such-ref', $output);
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY));
    }

    public function testWhenTheRevisionCannotBeWrittenTheDocumentIsNotPinnedEither(): void
    {
        $this->commitDocument(self::DOCUMENT);
        $before = (string) file_get_contents($this->package(self::CONTRACT_FILE));
        // A directory where the revision's temporary file would go makes
        // that write fail whoever runs the suite.
        mkdir($this->package(self::CONTRACT_FILE . self::PINNING));

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode, $output);
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY));
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY . self::PINNING));
        self::assertSame($before, file_get_contents($this->package(self::CONTRACT_FILE)));
    }

    public function testWhenTheDocumentCannotBeRenamedIntoPlaceTheRevisionIsPutBack(): void
    {
        $this->commitDocument(self::DOCUMENT);
        $before = (string) file_get_contents($this->package(self::CONTRACT_FILE));
        // A directory with something in it where the document goes: both
        // temporary files are written and the revision is renamed into
        // place before the document's rename fails, whoever runs the suite.
        mkdir($this->package(self::DOCUMENT_COPY));
        touch($this->package(self::DOCUMENT_COPY . '/kept'));

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode, $output);
        self::assertStringContainsString(self::DOCUMENT_COPY, $output);
        self::assertSame($before, file_get_contents($this->package(self::CONTRACT_FILE)));
        self::assertFileExists($this->package(self::DOCUMENT_COPY . '/kept'));
        self::assertSame([basename(self::CONTRACT_FILE)], array_values(array_diff((array) scandir(dirname($this->package(self::CONTRACT_FILE))), ['.', '..'])));
        self::assertSame([basename(self::DOCUMENT_COPY)], array_values(array_diff((array) scandir(dirname($this->package(self::DOCUMENT_COPY))), ['.', '..'])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leftovers(): iterable
    {
        foreach ([self::CONTRACT_FILE, self::DOCUMENT_COPY] as $file) {
            foreach ([self::PINNING, self::UNPINNED] as $suffix) {
                yield $file . $suffix => [$file . $suffix];
            }
        }
    }

    #[DataProvider('leftovers')]
    public function testAFileLeftByAnEarlierRunThatFailedIsRefusedBeforeAnythingChanges(string $leftover): void
    {
        $this->commitDocument(self::DOCUMENT);
        $before = (string) file_get_contents($this->package(self::CONTRACT_FILE));
        file_put_contents($this->package($leftover), "kept\n");

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode, $output);
        self::assertStringContainsString($leftover, $output);
        self::assertSame("kept\n", file_get_contents($this->package($leftover)));
        self::assertSame($before, file_get_contents($this->package(self::CONTRACT_FILE)));
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY));
    }

    public function testAPinnedFileKeepsItsMode(): void
    {
        $this->commitDocument(self::DOCUMENT);
        chmod($this->package(self::CONTRACT_FILE), 0o640);

        [$exitCode, $output] = $this->pin('main');

        self::assertSame(0, $exitCode, $output);
        clearstatcache();
        self::assertSame(0o640, fileperms($this->package(self::CONTRACT_FILE)) & 0o7777);
    }

    public function testAFilePutBackKeepsItsMode(): void
    {
        $this->commitDocument(self::DOCUMENT);
        chmod($this->package(self::CONTRACT_FILE), 0o640);
        // As above: the revision is renamed into place before the
        // document's rename fails, so the revision is put back.
        mkdir($this->package(self::DOCUMENT_COPY));
        touch($this->package(self::DOCUMENT_COPY . '/kept'));

        [$exitCode, $output] = $this->pin('main');

        self::assertNotSame(0, $exitCode, $output);
        clearstatcache();
        self::assertSame(0o640, fileperms($this->package(self::CONTRACT_FILE)) & 0o7777);
    }

    public function testAPinThatSucceedsLeavesNoBackupAndWarnsOfNone(): void
    {
        $this->commitDocument(self::DOCUMENT);

        [$exitCode, $output] = $this->pin('main');

        self::assertSame(0, $exitCode, $output);
        self::assertStringNotContainsString('Warning', $output);
        self::assertFileDoesNotExist($this->package(self::CONTRACT_FILE . self::UNPINNED));
        self::assertFileDoesNotExist($this->package(self::DOCUMENT_COPY . self::UNPINNED));
    }

    /**
     * A backup that cannot be removed once every file is in place is left
     * with a warning that names it: the pin succeeded, so it is safe to
     * delete, though the next run refuses to start until it is gone. A
     * directory stands in for a backup that cannot be removed, whoever runs
     * the suite.
     */
    public function testABackupThatCannotBeRemovedAfterAPinIsNamedAsSafeToDelete(): void
    {
        require_once self::own(self::TOOL);
        $removed = $this->package(self::CONTRACT_FILE . self::UNPINNED);
        $stuck = $this->package(self::DOCUMENT_COPY . self::UNPINNED);
        file_put_contents($removed, "original\n");
        mkdir($stuck);

        $warnings = \removeBackups([$removed, $stuck]);

        self::assertFileDoesNotExist($removed);
        self::assertCount(1, $warnings);
        self::assertStringContainsString($stuck, $warnings[0]);
        self::assertStringContainsString('safe to delete', $warnings[0]);
    }

    public function testTheUsageNamesEveryOption(): void
    {
        [, $output] = $this->execute([PHP_BINARY, self::own(self::TOOL)]);

        self::assertStringContainsString('--root=', $output);
    }

    public function testBothArgumentsAreRequired(): void
    {
        [$exitCode, $output] = $this->execute([PHP_BINARY, self::own(self::TOOL), '--root=' . $this->package(), $this->site()]);

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('Usage', $output);
    }

    /**
     * A path in this package, the one under test.
     */
    private static function own(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . $path;
    }

    /**
     * A path in the scratch copy of the package the tool pins, or its root.
     */
    private function package(string $path = ''): string
    {
        return $this->scratch . '/package' . ($path === '' ? '' : '/' . $path);
    }

    /**
     * A path in the scratch checkout of the site software, or its root.
     */
    private function site(string $path = ''): string
    {
        return $this->scratch . '/site' . ($path === '' ? '' : '/' . $path);
    }

    private function commitDocument(string $contents): string
    {
        if (!is_dir($this->site('.git'))) {
            $this->git('init', '--quiet', '--initial-branch=main');
        }
        file_put_contents($this->site(self::SITE_DOCUMENT), $contents);
        $this->git('add', self::SITE_DOCUMENT);
        $this->git('-c', 'user.name=t', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'document');

        return trim($this->git('rev-parse', 'HEAD'));
    }

    /**
     * @return array{int, string}
     */
    private function pin(string $ref): array
    {
        return $this->execute([PHP_BINARY, self::own(self::TOOL), '--root=' . $this->package(), $this->site(), $ref]);
    }

    private function git(string ...$arguments): string
    {
        [$exitCode, $output] = $this->execute(['git', '-C', $this->site(), ...array_values($arguments)]);
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

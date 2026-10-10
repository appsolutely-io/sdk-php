<?php

declare(strict_types=1);

/*
 * Pins the Site API document this client is held to:
 *
 *     composer pin-contract -- <site-software checkout> <ref>
 *
 * copies `docs/api/openapi.yaml` as it is at <ref> (a commit, a branch or a
 * tag) into tests/Contract/openapi.yaml and writes the commit <ref> names into
 * Contract::REVISION. Both are written by the same run so they cannot name
 * different commits. Nothing is written when either cannot be read.
 */

const DOCUMENT = 'docs/api/openapi.yaml';
const REVISION_LINE = '/public const string REVISION = \'[0-9a-f]*\';/';

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/**
 * Runs git without the GIT_* variables a hook would pass down, which would
 * point it at this repository rather than the checkout named.
 *
 * @param list<string> $arguments
 * @return array{int, string, string}
 */
function git(string $checkout, array $arguments): array
{
    $environment = [];
    foreach (getenv() as $name => $value) {
        if (!str_starts_with($name, 'GIT_')) {
            $environment[$name] = $value;
        }
    }

    $process = proc_open(['git', '-C', $checkout, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (!is_resource($process)) {
        fail('git could not be started.');
    }
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output, $error];
}

function write(string $path, string $contents): void
{
    $temporary = $path . '.pinning';
    if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) {
        fail(sprintf('%s could not be written.', $path));
    }
}

$root = dirname(__DIR__);
$arguments = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--root=')) {
        $root = substr($argument, strlen('--root='));
    } else {
        $arguments[] = $argument;
    }
}

if (count($arguments) !== 2) {
    fail("Usage: composer pin-contract -- <site-software checkout> <ref>\n\nCopies " . DOCUMENT . ' at <ref> into tests/Contract/openapi.yaml and records the commit in Contract::REVISION.');
}
[$checkout, $ref] = $arguments;

[$exitCode, $commit, $error] = git($checkout, ['rev-parse', '--verify', '--quiet', '--end-of-options', $ref . '^{commit}']);
$commit = trim($commit);
if ($exitCode !== 0 || preg_match('/^[0-9a-f]{40}$/D', $commit) !== 1) {
    fail(sprintf('"%s" names no commit in %s. %s', $ref, $checkout, trim($error)));
}

[$exitCode, $document, $error] = git($checkout, ['show', $commit . ':' . DOCUMENT]);
if ($exitCode !== 0 || $document === '') {
    fail(sprintf('%s has no %s at %s. %s', $checkout, DOCUMENT, $commit, trim($error)));
}

$contractPath = $root . '/src/Contract.php';
$contract = file_get_contents($contractPath);
if ($contract === false) {
    fail(sprintf('%s could not be read.', $contractPath));
}
$pinned = preg_replace(REVISION_LINE, "public const string REVISION = '" . $commit . "';", $contract, -1, $count);
if ($pinned === null || $count !== 1) {
    fail(sprintf('%s does not hold exactly one REVISION constant to write.', $contractPath));
}

write($root . '/tests/Contract/openapi.yaml', $document);
write($contractPath, $pinned);

fwrite(STDOUT, sprintf("Pinned %s at %s (%s).\n", DOCUMENT, $commit, $ref));

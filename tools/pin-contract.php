<?php

declare(strict_types=1);

/*
 * Pins the Site API document this client is held to:
 *
 *     composer pin-contract -- [--root=<package>] <site-software checkout> <ref>
 *
 * copies `docs/api/openapi.yaml` as it is at <ref> (a commit, a branch or a
 * tag) into tests/Contract/openapi.yaml and writes the commit <ref> names into
 * Contract::REVISION. Both are written by the same run so they cannot name
 * different commits: both are written to temporary files first and only then
 * renamed into place, and the originals are backed up first and put back
 * when a rename fails, so nothing is changed when either cannot be read or
 * written. `--root=` pins another copy of this package than the one the tool
 * sits in.
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

/**
 * Writes every file beside its path first, then backs up each original and
 * renames the new files into place only once all are written. A failure
 * before the renames removes what was written; a rename that fails after
 * others succeeded puts the originals back, so the files change together
 * or not at all.
 *
 * @param array<string, string> $files contents by path
 */
function writeAll(array $files): void
{
    $temporaries = [];
    foreach ($files as $path => $contents) {
        $temporary = $path . '.pinning';
        if (@file_put_contents($temporary, $contents) !== strlen($contents)) {
            if (is_file($temporary)) {
                $temporaries[] = $temporary;
            }
            removeAll($temporaries);
            fail(sprintf('%s could not be written.', $path));
        }
        $temporaries[] = $temporary;
    }

    $backups = [];
    foreach (array_keys($files) as $path) {
        if (is_file($path)) {
            if (!@copy($path, $path . '.unpinned')) {
                removeAll([...$temporaries, ...array_values($backups), $path . '.unpinned']);
                fail(sprintf('%s could not be backed up.', $path));
            }
            $backups[$path] = $path . '.unpinned';
        }
    }

    $replaced = [];
    foreach (array_keys($files) as $path) {
        if (!@rename($path . '.pinning', $path)) {
            $left = restoreAll($replaced, $backups);
            removeAll([...$temporaries, ...array_values(array_diff_key($backups, $left))]);
            fail(implode(' ', [sprintf('%s could not be written.', $path), ...array_values($left)]));
        }
        $replaced[] = $path;
    }

    removeAll(array_values($backups));
}

/**
 * Puts back the original of each path already replaced, or removes the
 * path when there was none.
 *
 * @param list<string> $replaced
 * @param array<string, string> $backups backup paths by original path
 * @return array<string, string> what could not be undone, by path, said for the user
 */
function restoreAll(array $replaced, array $backups): array
{
    $left = [];
    foreach ($replaced as $path) {
        if (isset($backups[$path])) {
            if (!@rename($backups[$path], $path)) {
                $left[$path] = sprintf('%s could not be put back; the original is in %s.', $path, $backups[$path]);
            }
        } elseif (!@unlink($path)) {
            $left[$path] = sprintf('%s was new and could not be removed.', $path);
        }
    }

    return $left;
}

/**
 * @param list<string> $paths
 */
function removeAll(array $paths): void
{
    foreach ($paths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

const USAGE = "Usage: composer pin-contract -- [--root=<package>] <site-software checkout> <ref>\n\nCopies " . DOCUMENT . " at <ref> into tests/Contract/openapi.yaml and records the commit in Contract::REVISION.\n--root=<package> pins another copy of this package than the one the tool sits in.";

$root = dirname(__DIR__);
$arguments = [];
$given = $_SERVER['argv'] ?? null;
if (!is_array($given)) {
    fail(USAGE);
}
foreach (array_slice($given, 1) as $argument) {
    if (!is_string($argument)) {
        continue;
    }
    if (str_starts_with($argument, '--root=')) {
        $root = substr($argument, strlen('--root='));
    } else {
        $arguments[] = $argument;
    }
}

if (count($arguments) !== 2) {
    fail(USAGE);
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

writeAll([
    $contractPath => $pinned,
    $root . '/tests/Contract/openapi.yaml' => $document,
]);

fwrite(STDOUT, sprintf("Pinned %s at %s (%s).\n", DOCUMENT, $commit, $ref));

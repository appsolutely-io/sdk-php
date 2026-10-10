<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

/**
 * A record as the site serves it, one file per shape under Fixtures/Site:
 * the fields in the site's order, null where it sends null, times as RFC
 * 3339 UTC to the second, ids as strings and amounts as integer minor units.
 * The site serialises a record the same way in an API answer and in a
 * webhook delivery's `data`, so tests of both read the same file.
 */
final class SiteFixture
{
    /**
     * The record decoded, with top-level members replaced or added.
     *
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public static function record(string $shape, array $changes = []): array
    {
        $record = json_decode(self::bytes($shape), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($record)) {
            throw new \LogicException(sprintf('Fixtures/Site/%s.json is not a JSON object.', $shape));
        }

        $members = [];
        foreach ($record as $name => $value) {
            $members[(string) $name] = $value;
        }

        return [...$members, ...$changes];
    }

    /**
     * The record as JSON text: the file's bytes, or, with changes, the
     * record re-encoded with top-level members replaced or added and every
     * empty object kept an object.
     *
     * @param array<string, mixed> $changes
     */
    public static function json(string $shape, array $changes = []): string
    {
        if ($changes === []) {
            return self::bytes($shape);
        }

        $record = json_decode(self::bytes($shape), false, 512, JSON_THROW_ON_ERROR);
        if (!$record instanceof \stdClass) {
            throw new \LogicException(sprintf('Fixtures/Site/%s.json is not a JSON object.', $shape));
        }
        foreach ($changes as $name => $value) {
            $record->{$name} = $value;
        }

        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function bytes(string $shape): string
    {
        $bytes = file_get_contents(dirname(__DIR__) . '/Fixtures/Site/' . $shape . '.json');
        if ($bytes === false) {
            throw new \LogicException(sprintf('There is no Fixtures/Site/%s.json.', $shape));
        }

        return trim($bytes);
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use Appsolutely\Sdk\Exception\UnexpectedPayloadException;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Support\Untrusted;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reads the typed fields of one JSON object in a delivery's `data`, and
 * remembers which it read, so whatever the site sends beyond them stays
 * readable as extra fields.
 *
 * A required field that is absent, null or of another type is refused with
 * the event type and the field's path; a nullable field that is absent reads
 * as null, so a field the site stops sending empty is not a failure.
 *
 * @internal
 */
final class Fields
{
    /**
     * RFC 3339 section 5.6: a full date, `T`, a full time with an optional
     * fraction, and `Z` or a numeric offset. The letters may be lower case.
     */
    private const string RFC_3339 = '/^(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}:\d{2})(?:\.(\d+))?([Zz]|[+-]\d{2}:\d{2})$/';

    /** @var array<string, true> */
    private array $read = [];

    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        private readonly array $values,
        private readonly string $type,
        private readonly string $path,
    ) {}

    /**
     * @param array<string, mixed> $data a verified envelope's `data`
     */
    public static function of(array $data, string $type): self
    {
        return new self($data, $type, 'data');
    }

    /**
     * The same object without fields another reader owns, such as the
     * `payment` an `order.paid` delivery adds beside the order's own fields.
     */
    public function except(string ...$keys): self
    {
        return new self(array_diff_key($this->values, array_flip($keys)), $this->type, $this->path);
    }

    public function string(string $name): string
    {
        return $this->nullableString($name) ?? throw $this->missing($name);
    }

    public function nullableString(string $name): ?string
    {
        $value = $this->value($name);
        if ($value !== null && !is_string($value)) {
            throw $this->refused($name, 'is not a string');
        }

        return $value;
    }

    public function int(string $name): int
    {
        return $this->nullableInt($name) ?? throw $this->missing($name);
    }

    public function nullableInt(string $name): ?int
    {
        $value = $this->value($name);
        if ($value !== null && !is_int($value)) {
            throw $this->refused($name, 'is not an integer');
        }

        return $value;
    }

    public function bool(string $name): bool
    {
        $value = $this->value($name) ?? throw $this->missing($name);
        if (!is_bool($value)) {
            throw $this->refused($name, 'is not a boolean');
        }

        return $value;
    }

    public function time(string $name): DateTimeImmutable
    {
        return $this->nullableTime($name) ?? throw $this->missing($name);
    }

    /**
     * The site writes every time in UTC to the second; an offset or a
     * fraction is still read, and the result is always in UTC.
     */
    public function nullableTime(string $name): ?DateTimeImmutable
    {
        $value = $this->nullableString($name);
        if ($value === null) {
            return null;
        }

        if (preg_match(self::RFC_3339, $value, $parts) !== 1) {
            throw $this->refused($name, 'is not an RFC 3339 date-time');
        }

        $offset = strtoupper($parts[4]) === 'Z' ? '+00:00' : $parts[4];
        $fraction = str_pad(substr($parts[3], 0, 6), 6, '0');
        $normalised = $parts[1] . 'T' . $parts[2] . '.' . $fraction . $offset;

        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $normalised);
        // createFromFormat() rolls an impossible date over (13th month, 31
        // September, hour 24) rather than failing, so the result is written
        // back and compared.
        if ($time === false || $time->format('Y-m-d\TH:i:s.uP') !== $normalised) {
            throw $this->refused($name, 'is not an RFC 3339 date-time');
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }

    public function object(string $name): self
    {
        return $this->nullableObject($name) ?? throw $this->missing($name);
    }

    public function nullableObject(string $name): ?self
    {
        $map = $this->nullableMap($name);

        return $map === null ? null : new self($map, $this->type, $this->path . '.' . $name);
    }

    /**
     * A JSON object whose members are not typed further, such as the values
     * a form entry holds. `{}` and `[]` both decode to an empty array.
     *
     * @return array<string, mixed>|null
     */
    public function nullableMap(string $name): ?array
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->refused($name, 'is not an object');
        }

        return Json::stringKeys($value);
    }

    /**
     * A JSON object of integers, such as an account's totals, which the site
     * sends as `{}` when there are none.
     *
     * @return array<string, int>
     */
    public function intMap(string $name): array
    {
        $map = $this->nullableMap($name) ?? throw $this->missing($name);

        $integers = [];
        foreach ($map as $member => $value) {
            if (!is_int($value)) {
                throw $this->refused($name . '.' . Untrusted::text($member), 'is not an integer');
            }
            $integers[$member] = $value;
        }

        return $integers;
    }

    /**
     * @return list<self>
     */
    public function objects(string $name): array
    {
        return $this->nullableObjects($name) ?? throw $this->missing($name);
    }

    /**
     * @return list<self>|null
     */
    public function nullableObjects(string $name): ?array
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->refused($name, 'is not a list');
        }

        $objects = [];
        foreach ($value as $index => $member) {
            if (!is_array($member) || ($member !== [] && array_is_list($member))) {
                throw $this->refused($name . '[' . $index . ']', 'is not an object');
            }
            $objects[] = new self(Json::stringKeys($member), $this->type, $this->path . '.' . $name . '[' . $index . ']');
        }

        return $objects;
    }

    /**
     * The fields no reader asked for: what the site added after this client
     * was written.
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return array_diff_key($this->values, $this->read);
    }

    private function value(string $name): mixed
    {
        $this->read[$name] = true;

        return $this->values[$name] ?? null;
    }

    private function missing(string $name): UnexpectedPayloadException
    {
        return $this->refused($name, 'is missing');
    }

    private function refused(string $field, string $problem): UnexpectedPayloadException
    {
        return new UnexpectedPayloadException(sprintf(
            'The %s delivery\'s %s.%s %s.',
            Untrusted::text($this->type),
            $this->path,
            $field,
            $problem,
        ));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Appsolutely\Sdk\Exception\UnexpectedPayloadException;
use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\Json;
use Appsolutely\Sdk\Support\Untrusted;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * One JSON object the site sent, an API answer or a webhook delivery's
 * `data`, read field by field into a model. The site serialises a record
 * the same way on both, so one reader serves both.
 *
 * Each kind of value has one reading: an identifier or a text is a string,
 * a time is RFC 3339 read as an instant in UTC, money is an integer count of
 * minor units. A required field that is absent, null or of another type is
 * refused, naming where it is, rather than read as something else; a
 * nullable field that is absent reads as null. The value itself never
 * reaches the message: it may be personal data.
 *
 * The members read are remembered twice: per object, so what the site sends
 * beyond them stays readable as extra(); and as paths across the whole
 * answer, so the contract test can hold every model to its schema.
 *
 * @internal
 */
final class Fields
{
    /**
     * RFC 3339 section 5.6: a full date, `T`, a full time with an optional
     * fraction, and `Z` or a numeric offset. The letters may be lower case.
     */
    private const string RFC_3339 = '/^(\d{4})-(\d{2})-(\d{2})[Tt](\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(?:([Zz])|([+-])(\d{2}):(\d{2}))$/D';

    /** @var array<string, true> the members of this object read so far */
    private array $read = [];

    /**
     * @param array<string, mixed> $object
     * @param string $source what the object came in, for a refusal: a schema name, or an event type
     * @param bool $delivery whether it is a webhook delivery's data rather than an API answer
     * @param \ArrayObject<int, string> $reads shared by an object and the objects nested in it
     * @param string $path the object's place in what was sent, for a refusal: `items[1]`
     * @param string $readPath the object's place in its schema, for the record: `items[]`
     */
    private function __construct(
        private readonly array $object,
        private readonly string $source,
        private readonly bool $delivery,
        private readonly \ArrayObject $reads,
        private readonly string $path,
        private readonly string $readPath,
    ) {}

    /**
     * An object of a Site API answer; a field that breaks the schema is an
     * UnexpectedResponseException.
     *
     * @param array<string, mixed> $object
     * @param string $schema the name of the document's schema the object follows
     */
    public static function of(#[\SensitiveParameter] array $object, string $schema): self
    {
        return new self($object, $schema, false, new \ArrayObject(), '', '');
    }

    /**
     * A verified delivery's `data`; a field that breaks the shape its type is
     * sent with is an UnexpectedPayloadException.
     *
     * @param array<string, mixed> $data
     */
    public static function ofDelivery(#[\SensitiveParameter] array $data, string $type): self
    {
        return new self($data, $type, true, new \ArrayObject(), 'data', '');
    }

    /**
     * The same object without members another reader owns, such as the
     * `payment` an `order.paid` delivery adds beside the order's own fields.
     */
    public function except(string ...$names): self
    {
        return new self(array_diff_key($this->object, array_flip($names)), $this->source, $this->delivery, $this->reads, $this->path, $this->readPath);
    }

    /**
     * The members no reader asked for, as decoded: what the site added after
     * this client was written.
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return array_diff_key($this->object, $this->read);
    }

    /**
     * The fields read so far, as paths through nested objects (`items[].id`).
     *
     * @return list<string>
     */
    public function reads(): array
    {
        return array_values($this->reads->getArrayCopy());
    }

    public function string(string $name): string
    {
        return $this->nullableString($name) ?? $this->missing($name);
    }

    public function nullableString(string $name): ?string
    {
        $value = $this->value($name);
        if ($value !== null && !is_string($value)) {
            $this->refuse($name, 'a string', $value);
        }

        return $value;
    }

    public function int(string $name): int
    {
        return $this->nullableInt($name) ?? $this->missing($name);
    }

    public function nullableInt(string $name): ?int
    {
        $value = $this->value($name);
        if ($value !== null && !is_int($value)) {
            $this->refuse($name, 'an integer', $value);
        }

        return $value;
    }

    public function bool(string $name): bool
    {
        return $this->nullableBool($name) ?? $this->missing($name);
    }

    public function nullableBool(string $name): ?bool
    {
        $value = $this->value($name);
        if ($value !== null && !is_bool($value)) {
            $this->refuse($name, 'a boolean', $value);
        }

        return $value;
    }

    public function time(string $name): DateTimeImmutable
    {
        return $this->nullableTime($name) ?? $this->missing($name);
    }

    /**
     * An RFC 3339 date-time with an offset (`2026-10-08T12:34:56Z`), as the
     * same instant in UTC. The site writes every time in UTC to the second;
     * an offset or a fraction is still read, the fraction to the
     * microsecond. A leap second (`:60`) is refused: a DateTimeImmutable
     * cannot hold one.
     */
    public function nullableTime(string $name): ?DateTimeImmutable
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || preg_match(self::RFC_3339, $value, $parts, PREG_UNMATCHED_AS_NULL) !== 1) {
            $this->refuse($name, 'an RFC 3339 date-time with an offset', $value);
        }
        [, $year, $month, $day, $hour, $minute, $second] = $parts;
        $fraction = $parts[7] ?? '';
        $zulu = $parts[8] !== null;
        $offsetHours = $parts[10] ?? '00';
        $offsetMinutes = $parts[11] ?? '00';
        if (!checkdate((int) $month, (int) $day, (int) $year)
            || (int) $hour > 23 || (int) $minute > 59 || (int) $second > 59
            || (int) $offsetHours > 23 || (int) $offsetMinutes > 59) {
            $this->refuse($name, 'an RFC 3339 date-time with an offset', $value);
        }

        $offset = $zulu ? '+00:00' : ($parts[9] ?? '+') . $offsetHours . ':' . $offsetMinutes;
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', sprintf(
            '%s-%s-%sT%s:%s:%s.%s%s',
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second,
            str_pad(substr($fraction, 0, 6), 6, '0'),
            $offset,
        ));
        if ($time === false) {
            $this->refuse($name, 'an RFC 3339 date-time with an offset', $value);
        }

        return $time->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @return list<string>
     */
    public function strings(string $name): array
    {
        $values = $this->list($name, 'a list of strings');
        foreach ($values as $value) {
            if (!is_string($value)) {
                $this->refuse($name, 'a list of strings', $values);
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * A free-form object, such as a form entry's answers, kept as decoded.
     * `{}` and `[]` both decode to an empty array.
     *
     * @return array<string, mixed>
     */
    public function map(string $name): array
    {
        return $this->nullableMap($name) ?? $this->missing($name);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nullableMap(string $name): ?array
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }
        if (!self::isObject($value)) {
            $this->refuse($name, 'an object', $value);
        }

        return Json::stringKeys($value);
    }

    /**
     * An object of integers, such as an account's totals, which the site
     * sends as `{}` when there are none.
     *
     * @return array<string, int>
     */
    public function intMap(string $name): array
    {
        $integers = [];
        foreach ($this->map($name) as $member => $value) {
            if (!is_int($value)) {
                $this->refuse($name . '.' . $member, 'an integer', $value);
            }
            $integers[$member] = $value;
        }

        return $integers;
    }

    /**
     * A nested object, read with the same record and refusals.
     */
    public function object(string $name): self
    {
        return $this->nullableObject($name) ?? $this->missing($name);
    }

    public function nullableObject(string $name): ?self
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }
        if (!self::isObject($value)) {
            $this->refuse($name, 'an object', $value);
        }

        return new self(Json::stringKeys($value), $this->source, $this->delivery, $this->reads, $this->join($this->path, $name), $this->join($this->readPath, $name));
    }

    /**
     * A list of nested objects.
     *
     * @return list<self>
     */
    public function objects(string $name): array
    {
        $objects = [];
        foreach ($this->list($name, 'a list of objects') as $index => $value) {
            if (!self::isObject($value)) {
                $this->refuse($name . '[' . $index . ']', 'an object', $value);
            }
            $objects[] = new self(Json::stringKeys($value), $this->source, $this->delivery, $this->reads, $this->join($this->path, $name) . '[' . $index . ']', $this->join($this->readPath, $name) . '[]');
        }

        return $objects;
    }

    /**
     * @return list<mixed>
     */
    private function list(string $name, string $expected): array
    {
        $value = $this->value($name) ?? $this->missing($name);
        if (!is_array($value) || !array_is_list($value)) {
            $this->refuse($name, $expected, $value);
        }

        return $value;
    }

    private function value(string $name): mixed
    {
        $this->read[$name] = true;
        $this->reads[] = $this->join($this->readPath, $name);

        return $this->object[$name] ?? null;
    }

    /**
     * @phpstan-assert-if-true array<mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    private function join(string $path, string $name): string
    {
        return $path === '' ? $name : $path . '.' . $name;
    }

    private function missing(string $name): never
    {
        throw $this->refusal(
            sprintf('The %s delivery\'s %s is missing.', Untrusted::text($this->source), $this->where($name)),
            sprintf('The site answered a "%s" object without "%s", which the Site API document requires.', $this->source, $this->where($name)),
        );
    }

    private function refuse(string $name, string $expected, mixed $value): never
    {
        throw $this->refusal(
            sprintf('The %s delivery\'s %s is not %s.', Untrusted::text($this->source), $this->where($name), $expected),
            sprintf('The site answered a "%s" object whose "%s" is not %s (%s).', $this->source, $this->where($name), $expected, get_debug_type($value)),
        );
    }

    /**
     * The field's path, its last step printable whatever the site named it.
     */
    private function where(string $name): string
    {
        return $this->join($this->path, Untrusted::text($name));
    }

    /**
     * The exception for what was read, with the message written for it: a
     * delivery's names its event type, an answer's names its schema.
     */
    private function refusal(string $inDelivery, string $inAnswer): RuntimeException
    {
        return $this->delivery ? new UnexpectedPayloadException($inDelivery) : new UnexpectedResponseException($inAnswer, 0);
    }
}

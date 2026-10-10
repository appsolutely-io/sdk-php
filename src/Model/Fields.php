<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Appsolutely\Sdk\Exception\UnexpectedResponseException;
use Appsolutely\Sdk\Http\Json;
use DateTimeImmutable;
use DateTimeZone;

/**
 * One JSON object of a Site API answer, read field by field into a model.
 * Each kind of value has one reading: an identifier or a text is a string,
 * a time is RFC 3339 read as an instant in UTC, money is an integer count
 * of minor units. A field the site sent in another shape is refused, naming
 * the schema and the path to the field, rather than read as something else.
 *
 * The names read are recorded, so the contract test can hold every model to
 * the schema the document gives it.
 *
 * @internal
 */
final class Fields
{
    /**
     * @param array<string, mixed> $object
     * @param \ArrayObject<int, string> $reads shared by a model and the objects nested in it
     * @param string $path the object's place in the answer, for a refusal: `items[1]`
     * @param string $readPath the object's place in its schema, for the record: `items[]`
     */
    private function __construct(
        private readonly array $object,
        private readonly string $schema,
        private readonly \ArrayObject $reads,
        private readonly string $path,
        private readonly string $readPath,
    ) {}

    /**
     * @param array<string, mixed> $object
     * @param string $schema the name of the document's schema the object follows, for a refusal
     */
    public static function of(array $object, string $schema): self
    {
        return new self($object, $schema, new \ArrayObject(), '', '');
    }

    /**
     * The object as the site sent it, members this client has no property
     * for included.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->object;
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
        return $this->optionalString($name) ?? $this->missing($name);
    }

    public function optionalString(string $name): ?string
    {
        $value = $this->value($name);
        if ($value !== null && !is_string($value)) {
            $this->refuse($name, 'a string');
        }

        return $value;
    }

    public function int(string $name): int
    {
        return $this->optionalInt($name) ?? $this->missing($name);
    }

    public function optionalInt(string $name): ?int
    {
        $value = $this->value($name);
        if ($value !== null && !is_int($value)) {
            $this->refuse($name, 'an integer');
        }

        return $value;
    }

    public function bool(string $name): bool
    {
        return $this->optionalBool($name) ?? $this->missing($name);
    }

    public function optionalBool(string $name): ?bool
    {
        $value = $this->value($name);
        if ($value !== null && !is_bool($value)) {
            $this->refuse($name, 'a boolean');
        }

        return $value;
    }

    public function time(string $name): DateTimeImmutable
    {
        return $this->optionalTime($name) ?? $this->missing($name);
    }

    /**
     * An RFC 3339 date-time with an offset (`2026-10-08T12:34:56Z`), as the
     * same instant in UTC.
     */
    public function optionalTime(string $name): ?DateTimeImmutable
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }

        $match = is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})[Tt](\d{2}):(\d{2}):(\d{2})(?:\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/D', $value, $parts) === 1;
        if (!$match || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59) {
            $this->refuse($name, 'an RFC 3339 date-time with an offset');
        }

        $offset = strtoupper($parts[7]) === 'Z' ? '+00:00' : $parts[7];
        $time = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', sprintf('%s-%s-%sT%s:%s:%s%s', $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $offset));
        if ($time === false) {
            $this->refuse($name, 'an RFC 3339 date-time with an offset');
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
                $this->refuse($name, 'a list of strings');
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /**
     * A free-form object, such as a form entry's answers, kept as decoded.
     *
     * @return array<string, mixed>
     */
    public function map(string $name): array
    {
        return $this->optionalMap($name) ?? $this->missing($name);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function optionalMap(string $name): ?array
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }
        if (!self::isObject($value)) {
            $this->refuse($name, 'an object');
        }

        return Json::stringKeys($value);
    }

    /**
     * A nested object, read with the same record and refusals.
     */
    public function object(string $name): self
    {
        return $this->optionalObject($name) ?? $this->missing($name);
    }

    public function optionalObject(string $name): ?self
    {
        $value = $this->value($name);
        if ($value === null) {
            return null;
        }
        if (!self::isObject($value)) {
            $this->refuse($name, 'an object');
        }

        return new self(Json::stringKeys($value), $this->schema, $this->reads, $this->join($this->path, $name), $this->join($this->readPath, $name));
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
                $this->refuse($name, 'a list of objects');
            }
            $objects[] = new self(Json::stringKeys($value), $this->schema, $this->reads, $this->join($this->path, $name) . '[' . $index . ']', $this->join($this->readPath, $name) . '[]');
        }

        return $objects;
    }

    /**
     * @return list<mixed>
     */
    private function list(string $name, string $expected): array
    {
        $value = $this->value($name);
        if ($value === null) {
            $this->missing($name);
        }
        if (!is_array($value) || !array_is_list($value)) {
            $this->refuse($name, $expected);
        }

        return $value;
    }

    private function value(string $name): mixed
    {
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
        throw new UnexpectedResponseException(sprintf('The site answered a "%s" object without "%s", which the Site API document requires.', $this->schema, $this->join($this->path, $name)), 0);
    }

    private function refuse(string $name, string $expected): never
    {
        // The value itself is left out: it may be personal data.
        throw new UnexpectedResponseException(sprintf(
            'The site answered a "%s" object whose "%s" is not %s (%s).',
            $this->schema,
            $this->join($this->path, $name),
            $expected,
            get_debug_type($this->object[$name] ?? null),
        ), 0);
    }
}

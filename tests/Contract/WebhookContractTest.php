<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\Events\TypedEvent;
use Appsolutely\Sdk\Webhooks\Events\UnknownEvent;
use Appsolutely\Sdk\Webhooks\EventType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Holds the typed webhook events to the pinned document's `webhooks`
 * section: every event type it describes is an EventType constant and no
 * constant names another, every one is read as a typed event rather than an
 * UnknownEvent, each typed event reads only and all of what its payload
 * schema defines, each field as the type and nullability it declares, and
 * Event reads every member of the envelope.
 */
final class WebhookContractTest extends TestCase
{
    use SchemaReads;

    /**
     * The component every event's envelope is composed from.
     */
    private const string ENVELOPE = 'WebhookEnvelope';

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentedTypes(): iterable
    {
        foreach (array_keys(Document::webhooks()) as $type) {
            yield $type => [$type];
        }
    }

    public function testEveryDocumentedTypeIsAnEventTypeAndNoOtherIs(): void
    {
        $documented = array_keys(Document::webhooks());
        sort($documented);
        $named = array_values(array_filter((new ReflectionClass(EventType::class))->getConstants(), is_string(...)));
        sort($named);

        self::assertNotSame([], $documented, 'The pinned document describes no webhooks.');
        self::assertSame($documented, $named, 'EventType names other event types than the document describes.');
    }

    /**
     * Each webhook is a POST of its own event schema: the envelope, with
     * `type` fixed to the webhook's event type and its `data`.
     */
    #[DataProvider('documentedTypes')]
    public function testEachEventIsTheEnvelopeWithItsTypeAndData(string $type): void
    {
        $event = Document::map(Document::schema(self::eventSchema($type)));
        $parts = Document::list($event['allOf'] ?? null);

        self::assertContains(['$ref' => '#/components/schemas/' . self::ENVELOPE], $parts, sprintf('%s is not composed from %s.', $type, self::ENVELOPE));
        $resolved = Document::resolve($event);
        self::assertSame($type, Document::map(Document::map($resolved['properties'] ?? null)['type'] ?? null)['const'] ?? null, sprintf('%s does not fix its envelope\'s type to itself.', $type));
        self::assertContains('data', Document::list($resolved['required'] ?? []), sprintf('%s does not require its data.', $type));
    }

    #[DataProvider('documentedTypes')]
    public function testEveryDocumentedTypeIsReadAsATypedEvent(string $type): void
    {
        $event = TypedEvent::from(self::envelope($type, self::sampleOf(self::dataSchema($type))));

        self::assertNotInstanceOf(UnknownEvent::class, $event, sprintf('%s, which the document describes, is read as an UnknownEvent.', $type));
    }

    /**
     * The typed event of each type is read from a sample of the payload
     * schema the document gives it, every field present and of its
     * documented type, and what it read is held to that schema as a model's
     * reads are held to its own.
     */
    #[DataProvider('documentedTypes')]
    public function testATypedEventReadsOnlyAndAllOfWhatItsPayloadSchemaDefines(string $type): void
    {
        $schema = self::dataSchema($type);
        $data = self::sampleOf($schema);
        $envelope = self::envelope($type, $data);
        $class = TypedEvent::from($envelope)::class;
        $read = [$class, 'read'];
        self::assertIsCallable($read, sprintf('%s, the typed event of %s, has no read().', $class, $type));

        $fields = Fields::ofDelivery($data, $type);
        $read($envelope, $fields);

        self::assertReadsMatch($schema, $fields->reads(), $class . ' (' . $type . ')');
        self::assertReadTypesMatch($schema, $fields->accessors(), $class . ' (' . $type . ')');
    }

    /**
     * Event holds every member of the envelope as a property of the same
     * name, `data` among them, and names each value the document enumerates
     * for one as a constant (`MODE_TEST`).
     */
    public function testTheEnvelopeIsReadWhole(): void
    {
        $envelope = Document::resolve(Document::schema(self::ENVELOPE));
        $members = array_map(strval(...), array_keys(Document::map($envelope['properties'] ?? null)));
        $properties = array_map(static fn(\ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(Event::class))->getProperties());

        $expected = [...$members, 'data'];
        sort($expected);
        sort($properties);
        self::assertSame($expected, $properties);

        $constants = (new ReflectionClass(Event::class))->getConstants();
        foreach (Document::map($envelope['properties'] ?? null) as $member => $declaration) {
            foreach (Document::list(Document::map($declaration)['enum'] ?? []) as $value) {
                self::assertIsString($value);
                $name = strtoupper($member . '_' . $value);
                self::assertSame($value, $constants[$name] ?? null, sprintf('Event has no constant %s for the envelope\'s %s "%s".', $name, $member, $value));
            }
        }
    }

    /**
     * The name of the event schema a webhook is sent as.
     */
    private static function eventSchema(string $type): string
    {
        $webhook = Document::webhooks()[$type] ?? self::fail(sprintf('The document describes no webhook %s.', $type));
        $body = Document::map(Document::map(Document::map(Document::map(Document::map($webhook['post'] ?? null)['requestBody'] ?? null)['content'] ?? null)['application/json'] ?? null)['schema'] ?? null);

        return Document::refName($body) ?? self::fail(sprintf('The webhook %s names no event schema.', $type));
    }

    /**
     * The name of the schema an event's `data` follows.
     */
    private static function dataSchema(string $type): string
    {
        $data = Document::map(Document::map(Document::resolve(Document::schema(self::eventSchema($type)))['properties'] ?? null)['data'] ?? null);

        return Document::refName($data) ?? self::fail(sprintf('The data of %s names no schema.', $type));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function envelope(string $type, array $data): Event
    {
        return new Event('msg_contract', $type, new DateTimeImmutable('2026-10-08T12:34:56Z'), 1, 'production', $data);
    }
}

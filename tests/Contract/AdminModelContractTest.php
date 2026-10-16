<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

use Appsolutely\Sdk\Admin\Model\AdminModel;
use Appsolutely\Sdk\Admin\Model\Article;
use Appsolutely\Sdk\Admin\Model\FormEntry;
use Appsolutely\Sdk\Admin\Model\Order;
use Appsolutely\Sdk\Admin\Model\Page;
use Appsolutely\Sdk\Admin\Model\Product;
use Appsolutely\Sdk\Admin\Model\WebhookDelivery;
use Appsolutely\Sdk\Model\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Each Administrator API model reads only what its schema in the pinned
 * Administrator API document defines, every field the schema requires, and
 * each with the accessor its declaration calls for, by the same rules the
 * Site API models are held to.
 */
final class AdminModelContractTest extends TestCase
{
    use SchemaReads;

    /** @var list<class-string<AdminModel>> */
    private const array MODELS = [Article::class, Page::class, Product::class, Order::class, FormEntry::class, WebhookDelivery::class];

    /**
     * @return iterable<string, array{class-string<AdminModel>}>
     */
    public static function models(): iterable
    {
        foreach (self::MODELS as $class) {
            yield (new ReflectionClass($class))->getShortName() => [$class];
        }
    }

    public function testEveryModelOfTheNamespaceIsHeldHere(): void
    {
        $found = [];
        foreach ((array) glob(dirname(__DIR__, 2) . '/src/Admin/Model/*.php') as $file) {
            $class = 'Appsolutely\\Sdk\\Admin\\Model\\' . basename((string) $file, '.php');
            if (class_exists($class) && (new ReflectionClass($class))->implementsInterface(AdminModel::class)) {
                $found[] = $class;
            }
        }
        sort($found);
        $held = self::MODELS;
        sort($held);

        self::assertSame($held, $found);
    }

    /**
     * @param class-string<AdminModel> $class
     */
    #[DataProvider('models')]
    public function testAModelReadsOnlyAndAllOfWhatItsSchemaDefines(string $class): void
    {
        $schema = $class::SCHEMA;
        $fields = Fields::of(self::sampleOf($schema), $schema);
        $class::from($fields);

        self::assertReadsMatch($schema, $fields->reads(), $class);
        self::assertReadTypesMatch($schema, $fields->accessors(), $class);
    }

    /**
     * Every value the document enumerates for a field a model reads is a
     * constant of the model, so a caller compares against a name, not a
     * literal.
     *
     * @param class-string<AdminModel> $class
     */
    #[DataProvider('models')]
    public function testEveryEnumeratedValueOfAFieldReadIsAConstant(string $class): void
    {
        $schema = $class::SCHEMA;
        $fields = Fields::of(self::sampleOf($schema), $schema);
        $class::from($fields);
        $constants = array_values((new ReflectionClass($class))->getConstants());
        $properties = Document::map(AdminDocument::schema($schema)['properties'] ?? null);

        foreach ($fields->reads() as $field) {
            foreach (Document::list(Document::map($properties[$field] ?? null)['enum'] ?? []) as $value) {
                if ($value !== null) {
                    self::assertContains($value, $constants, sprintf('%s reads %s but has no constant for %s.', $class, $field, var_export($value, true)));
                }
            }
        }
    }

    /**
     * @return class-string<Document>
     */
    protected static function document(): string
    {
        return AdminDocument::class;
    }

    /**
     * @return array<string, array<string, array{document: array<array-key, mixed>, site: mixed}>>
     */
    protected static function asTheSiteSends(): array
    {
        return [];
    }
}

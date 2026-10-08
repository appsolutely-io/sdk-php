<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Webhooks;

use Appsolutely\Sdk\Exception\NotSerializableException;
use Appsolutely\Sdk\Testing\WebhookFactory;
use Appsolutely\Sdk\Webhooks\Secret;
use Appsolutely\Sdk\Webhooks\Verifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A webhook signing secret, like the client secret in Config, never shows
 * in a debug dump or an error page and is never carried into a serialized
 * copy (a session, a cache, a queue payload).
 */
final class SecretExposureTest extends TestCase
{
    /** Printable key bytes, so a dump that leaked them could be searched for. */
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private static function secret(): string
    {
        return Secret::PREFIX . base64_encode(self::KEY);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function holders(): iterable
    {
        yield 'Verifier' => [new Verifier([self::secret()])];
        yield 'WebhookFactory' => [new WebhookFactory([self::secret()])];
        yield 'Secret' => [Secret::fromString(self::secret())];
    }

    #[DataProvider('holders')]
    public function testADumpShowsNeitherTheSecretNorItsKey(object $holder): void
    {
        ob_start();
        var_dump($holder);
        $dumps = [(string) ob_get_clean(), print_r($holder, true)];

        foreach ($dumps as $dump) {
            self::assertStringNotContainsString(self::KEY, $dump);
            self::assertStringNotContainsString(base64_encode(self::KEY), $dump);
            self::assertStringContainsString('[redacted]', $dump);
        }
    }

    #[DataProvider('holders')]
    public function testItRefusesToBeSerialized(object $holder): void
    {
        $this->expectException(NotSerializableException::class);

        serialize($holder);
    }

    public function testASerializedSecretIsNotRestored(): void
    {
        $this->expectException(NotSerializableException::class);

        unserialize(sprintf('O:%d:"%s":1:{s:3:"key";s:32:"%s";}', strlen(Secret::class), Secret::class, self::KEY));
    }
}

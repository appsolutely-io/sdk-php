<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Http\Json;
use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase
{
    /**
     * PHP keeps a member named like a decimal integer under an integer key,
     * whatever it is cast to, so every decoded object is keyed by array-key.
     */
    public function testAMemberNamedLikeANumberIsKeptUnderAnIntegerKey(): void
    {
        self::assertSame([7 => 1, 'a' => 2, '07' => 3], Json::decodeObject('{"7":1,"a":2,"07":3}'));
    }

    public function testAnythingButAnObjectIsNull(): void
    {
        self::assertNull(Json::decodeObject('[1,2]'));
        self::assertNull(Json::decodeObject('"text"'));
        self::assertNull(Json::decodeObject('{'));
        self::assertSame([], Json::decodeObject('{}'));
    }
}

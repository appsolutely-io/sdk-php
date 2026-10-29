<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Appsolutely\Sdk\Support\OptionalFields;
use PHPUnit\Framework\TestCase;

final class OptionalFieldsTest extends TestCase
{
    public function testOnlyNullMembersAreOmitted(): void
    {
        self::assertSame(['empty' => '', 'zero' => 0, 'disabled' => false, 'list' => [], 'nested' => ['keep' => null]], OptionalFields::withoutNulls(['absent' => null, 'empty' => '', 'zero' => 0, 'disabled' => false, 'list' => [], 'nested' => ['keep' => null]]));
        self::assertSame([], OptionalFields::withoutNulls(['absent' => null]));
    }
}

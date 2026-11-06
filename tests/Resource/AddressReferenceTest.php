<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Resource;

use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Testing\FakeClient;
use PHPUnit\Framework\TestCase;

final class AddressReferenceTest extends TestCase
{
    public function testAMemberAndTheBackendReadTheSameReferenceData(): void
    {
        $country = ['code' => 'NZ', 'name' => 'New Zealand', 'retired_at' => null];
        $fake = (new FakeClient())
            ->answerPage(Operation::ListAddressCountries, [$country])
            ->answerPage(Operation::ListAddressCountries, [$country]);

        $asMember = iterator_to_array($fake->forMember()->api()->addressReference()->countries(includeRetired: true), false);
        self::assertSame(FakeClient::MEMBER_TOKEN, $fake->lastCall(Operation::ListAddressCountries)->token);
        self::assertSame(['include_retired' => 'true', 'limit' => '25'], $fake->lastCall(Operation::ListAddressCountries)->query);

        $asBackend = iterator_to_array($fake->backend()->addressReference()->countries(), false);
        self::assertSame(FakeClient::BACKEND_TOKEN, $fake->lastCall(Operation::ListAddressCountries)->token);

        self::assertSame('NZ', $asMember[0]->code);
        self::assertSame('New Zealand', $asBackend[0]->name);
    }

    public function testFormatsAreasAndPostalCodesAreReadWithTheirFilters(): void
    {
        $fake = (new FakeClient())
            ->answerPage(Operation::ListAddressFormats, [['country' => 'GB', 'fields' => ['address', 'city', 'postcode'], 'required' => ['address', 'postcode'], 'labels' => ['province' => null, 'postcode' => 'Postcode', 'city' => 'Town', 'town' => null]]])
            ->answerPage(Operation::ListAddressAreas, [['code' => 'NZ-AUK', 'country' => 'NZ', 'parent' => null, 'type' => 'region', 'official_code' => 'AUK', 'name' => 'Auckland', 'retired_at' => null]])
            ->answerPage(Operation::ListAddressPostalCodes, [['country' => 'NZ', 'pattern' => '^\d{4}$', 'examples' => ['1010']]]);
        $reference = $fake->forMember()->api()->addressReference();

        $format = iterator_to_array($reference->formats('GB'), false)[0];
        self::assertSame(['address', 'postcode'], $format->required);
        self::assertSame('Town', $format->labels->city);
        self::assertNull($format->labels->province);

        $area = iterator_to_array($reference->areas('NZ', parent: 'NZ'), false)[0];
        self::assertSame('Auckland', $area->name);
        self::assertSame(['country' => 'NZ', 'parent' => 'NZ', 'limit' => '25'], $fake->lastCall(Operation::ListAddressAreas)->query);

        $postal = iterator_to_array($reference->postalCodes('NZ'), false)[0];
        self::assertSame(['1010'], $postal->examples);
    }
}

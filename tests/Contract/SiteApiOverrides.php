<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Contract;

/**
 * The Site API document's fields the models read as the site sends them.
 */
final class SiteApiOverrides
{
    /**
     * Fields the pinned document types otherwise than the site sends them,
     * where the model follows the site: sampled as the site sends them. Each
     * names what the document declares (its description aside), and the
     * override applies only while the document still declares exactly that,
     * so a document that changes, to agree with the site or otherwise, fails
     * until the entry is dropped or rewritten.
     *
     * The document leaves a subscription's `pending_period` an open object or
     * null; the site sends the period's start and where its payment stands.
     */
    public const array AS_THE_SITE_SENDS = [
        // The Site API document at d66073e65 allows pending tax and totals to be null.
        'Order' => [
            'tax_amount' => ['document' => ['type' => 'integer'], 'site' => null],
            'total_amount' => ['document' => ['type' => 'integer'], 'site' => null],
        ],
        // The Site API document at d66073e65 allows an unavailable seat member to be null.
        'Seat' => [
            'member' => ['document' => ['type' => 'string'], 'site' => null],
        ],
        'AccountSubscription' => [
            'pending_period' => ['document' => ['type' => ['object', 'null'], 'additionalProperties' => true], 'site' => ['start' => '2026-05-01T00:00:00Z', 'status' => 'pending']],
        ],
    ];
}

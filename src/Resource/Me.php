<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Member;
use Appsolutely\Sdk\Resource\Me\Addresses;
use Appsolutely\Sdk\Resource\Me\BillingEntry;
use Appsolutely\Sdk\Resource\Me\CheckoutSessions;
use Appsolutely\Sdk\Resource\Me\Entitlements;
use Appsolutely\Sdk\Resource\Me\Orders;
use Appsolutely\Sdk\Resource\Me\PortalSessions;
use Appsolutely\Sdk\Resource\Me\PushDevices;
use Appsolutely\Sdk\Resource\Me\Referral;
use Appsolutely\Sdk\Resource\Me\StorePurchases;
use Appsolutely\Sdk\Resource\Me\Subscriptions;

/**
 * The signed-in member and their own data.
 */
final readonly class Me
{
    /** @internal obtain it from Client::forMember()->api()->me() */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::GetMe)]
    public function get(): Member
    {
        return $this->caller->read(Operation::GetMe, Member::class);
    }

    #[Endpoint(Operation::UpdateMe)]
    public function update(string $name): Member
    {
        return $this->caller->read(Operation::UpdateMe, Member::class, body: ['name' => $name]);
    }

    public function addresses(): Addresses
    {
        return new Addresses($this->caller);
    }

    public function orders(): Orders
    {
        return new Orders($this->caller);
    }

    public function entitlements(): Entitlements
    {
        return new Entitlements($this->caller);
    }

    public function billingEntry(): BillingEntry
    {
        return new BillingEntry($this->caller);
    }

    public function pushDevices(): PushDevices
    {
        return new PushDevices($this->caller);
    }

    public function referral(): Referral
    {
        return new Referral($this->caller);
    }
    public function checkoutSessions(): CheckoutSessions
    {
        return new CheckoutSessions($this->caller);
    }

    public function portalSessions(): PortalSessions
    {
        return new PortalSessions($this->caller);
    }

    public function storePurchases(): StorePurchases
    {
        return new StorePurchases($this->caller);
    }

    public function subscriptions(): Subscriptions
    {
        return new Subscriptions($this->caller);
    }
    public function trials(): \Appsolutely\Sdk\Resource\Me\Trials
    {
        return new \Appsolutely\Sdk\Resource\Me\Trials($this->caller);
    }
    public function organisations(): \Appsolutely\Sdk\Resource\Me\Organisations
    {
        return new \Appsolutely\Sdk\Resource\Me\Organisations($this->caller);
    }

    public function announcements(): \Appsolutely\Sdk\Resource\Me\Announcements
    {
        return new \Appsolutely\Sdk\Resource\Me\Announcements($this->caller);
    }

    public function deletion(): \Appsolutely\Sdk\Resource\Me\Deletion
    {
        return new \Appsolutely\Sdk\Resource\Me\Deletion($this->caller);
    }

    public function messagePreferences(): \Appsolutely\Sdk\Resource\Me\MessagePreferences
    {
        return new \Appsolutely\Sdk\Resource\Me\MessagePreferences($this->caller);
    }

    public function messages(): \Appsolutely\Sdk\Resource\Me\Messages
    {
        return new \Appsolutely\Sdk\Resource\Me\Messages($this->caller);
    }

    public function realtime(): \Appsolutely\Sdk\Resource\Me\Realtime
    {
        return new \Appsolutely\Sdk\Resource\Me\Realtime($this->caller);
    }

    /** @return \Appsolutely\Sdk\Api\ConditionalResult<Member> */
    public function getConditional(string $ifNoneMatch): \Appsolutely\Sdk\Api\ConditionalResult
    {
        return $this->caller->conditionalRead(Operation::GetMe, Member::class, $ifNoneMatch);
    }
}

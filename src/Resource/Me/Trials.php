<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Me;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\MemberTrial;

final readonly class Trials
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::CreateMeTrial)]
    public function create(): MemberTrial
    {
        return $this->caller->read(Operation::CreateMeTrial, MemberTrial::class);
    }
}

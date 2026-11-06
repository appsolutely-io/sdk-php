<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

interface TokenGrant
{
    /** @return array<string, string> */
    public function fields(): array;
}

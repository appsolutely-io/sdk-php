<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\ApiResponse;
use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\OAuthDeviceAuthorization;
use Appsolutely\Sdk\Model\OAuthToken;
use Appsolutely\Sdk\Request\OAuth\ClientIdentity;
use Appsolutely\Sdk\Request\OAuth\DeviceAuthorizationRequest;
use Appsolutely\Sdk\Request\OAuth\RevocationRequest;
use Appsolutely\Sdk\Request\OAuth\TokenGrant;

final readonly class OAuth
{
    /** @internal */
    public function __construct(private Caller $caller) {}

    #[Endpoint(Operation::RequestDeviceAuthorization)]
    public function deviceAuthorization(#[\SensitiveParameter] DeviceAuthorizationRequest $request, ?ClientIdentity $identity = null): OAuthDeviceAuthorization
    {
        return OAuthDeviceAuthorization::from(Fields::of(Caller::objectOf($this->caller->form(Operation::RequestDeviceAuthorization, $request->fields(), $identity), Operation::RequestDeviceAuthorization), OAuthDeviceAuthorization::SCHEMA));
    }

    #[Endpoint(Operation::ExchangeOAuthToken)]
    public function exchange(#[\SensitiveParameter] TokenGrant $grant, ?ClientIdentity $identity = null): OAuthToken
    {
        return OAuthToken::from(Fields::of(Caller::objectOf($this->caller->form(Operation::ExchangeOAuthToken, $grant->fields(), $identity), Operation::ExchangeOAuthToken), OAuthToken::SCHEMA));
    }

    #[Endpoint(Operation::RevokeOAuthToken)]
    public function revoke(#[\SensitiveParameter] RevocationRequest $request, ?ClientIdentity $identity = null): ApiResponse
    {
        return $this->caller->form(Operation::RevokeOAuthToken, $request->fields(), $identity);
    }
}

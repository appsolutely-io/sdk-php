<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OpenIdConfiguration implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OpenIdConfiguration';

    /**
     * @param list<string> $scopesSupported
     * @param list<string> $claimsSupported
     * @param list<string> $responseTypesSupported
     * @param list<string> $responseModesSupported
     * @param list<string> $grantTypesSupported
     * @param list<string> $subjectTypesSupported
     * @param list<string> $idTokenSigningAlgValuesSupported
     * @param list<string> $promptValuesSupported
     * @param list<string> $tokenEndpointAuthMethodsSupported
     * @param list<string> $revocationEndpointAuthMethodsSupported
     * @param list<string> $codeChallengeMethodsSupported
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $userinfoEndpoint,
        public string $jwksUri,
        public string $revocationEndpoint,
        public string $deviceAuthorizationEndpoint,
        public string $endSessionEndpoint,
        public array $scopesSupported,
        public array $claimsSupported,
        public array $responseTypesSupported,
        public array $responseModesSupported,
        public array $grantTypesSupported,
        public array $subjectTypesSupported,
        public array $idTokenSigningAlgValuesSupported,
        public array $promptValuesSupported,
        public array $tokenEndpointAuthMethodsSupported,
        public array $revocationEndpointAuthMethodsSupported,
        public array $codeChallengeMethodsSupported,
        public bool $authorizationResponseIssParameterSupported,
        public bool $backchannelLogoutSupported,
        public bool $backchannelLogoutSessionSupported,
        public bool $frontchannelLogoutSupported,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            issuer: $json->string('issuer'),
            authorizationEndpoint: $json->string('authorization_endpoint'),
            tokenEndpoint: $json->string('token_endpoint'),
            userinfoEndpoint: $json->string('userinfo_endpoint'),
            jwksUri: $json->string('jwks_uri'),
            revocationEndpoint: $json->string('revocation_endpoint'),
            deviceAuthorizationEndpoint: $json->string('device_authorization_endpoint'),
            endSessionEndpoint: $json->string('end_session_endpoint'),
            scopesSupported: $json->strings('scopes_supported'),
            claimsSupported: $json->strings('claims_supported'),
            responseTypesSupported: $json->strings('response_types_supported'),
            responseModesSupported: $json->strings('response_modes_supported'),
            grantTypesSupported: $json->strings('grant_types_supported'),
            subjectTypesSupported: $json->strings('subject_types_supported'),
            idTokenSigningAlgValuesSupported: $json->strings('id_token_signing_alg_values_supported'),
            promptValuesSupported: $json->strings('prompt_values_supported'),
            tokenEndpointAuthMethodsSupported: $json->strings('token_endpoint_auth_methods_supported'),
            revocationEndpointAuthMethodsSupported: $json->strings('revocation_endpoint_auth_methods_supported'),
            codeChallengeMethodsSupported: $json->strings('code_challenge_methods_supported'),
            authorizationResponseIssParameterSupported: $json->bool('authorization_response_iss_parameter_supported'),
            backchannelLogoutSupported: $json->bool('backchannel_logout_supported'),
            backchannelLogoutSessionSupported: $json->bool('backchannel_logout_session_supported'),
            frontchannelLogoutSupported: $json->bool('frontchannel_logout_supported'),
            extra: $json->extra(),
        );
    }
}

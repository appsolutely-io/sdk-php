<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

use Appsolutely\Sdk\Exception\DiscoveryException;
use Appsolutely\Sdk\Http\SecureUrl;

/**
 * The parts of an OpenID Provider's discovery document this client relies on
 * (OpenID Connect Discovery 1.0 section 3, RFC 8414, RFC 9207), as
 * OpenIdClient::metadata() returns them.
 *
 * Only fromArray() builds one, so every instance has passed its checks,
 * the HTTPS rule for the endpoints among them.
 */
final readonly class ProviderMetadata
{
    /**
     * @param list<string> $idTokenSigningAlgValuesSupported
     * @param list<string>|null $codeChallengeMethodsSupported
     */
    private function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public array $idTokenSigningAlgValuesSupported,
        public ?string $userInfoEndpoint = null,
        public ?string $revocationEndpoint = null,
        public ?array $codeChallengeMethodsSupported = null,
        public bool $authorizationResponseIssParameterSupported = false,
    ) {}

    /**
     * @internal the client builds it from the discovery document it fetched
     *
     * @param array<string, mixed> $document
     */
    public static function fromArray(array $document): self
    {
        $iss = $document['authorization_response_iss_parameter_supported'] ?? false;
        if (!is_bool($iss)) {
            throw new DiscoveryException('The discovery document\'s authorization_response_iss_parameter_supported must be a boolean.');
        }

        return new self(
            issuer: self::requiredString($document, 'issuer'),
            authorizationEndpoint: self::requiredEndpoint($document, 'authorization_endpoint'),
            tokenEndpoint: self::requiredEndpoint($document, 'token_endpoint'),
            jwksUri: self::requiredEndpoint($document, 'jwks_uri'),
            idTokenSigningAlgValuesSupported: self::stringList($document, 'id_token_signing_alg_values_supported') ?? throw self::missing('id_token_signing_alg_values_supported'),
            userInfoEndpoint: self::optionalEndpoint($document, 'userinfo_endpoint'),
            revocationEndpoint: self::optionalEndpoint($document, 'revocation_endpoint'),
            codeChallengeMethodsSupported: self::stringList($document, 'code_challenge_methods_supported'),
            authorizationResponseIssParameterSupported: $iss,
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function requiredEndpoint(array $document, string $name): string
    {
        return self::optionalEndpoint($document, $name) ?? throw self::missing($name);
    }

    /**
     * Every endpoint is held to the issuer's rule (see SecureUrl), including
     * the refusal of what parsers read differently: the client sends its
     * secret, codes and tokens to them and takes its keys from one.
     *
     * @param array<string, mixed> $document
     */
    private static function optionalEndpoint(array $document, string $name): ?string
    {
        $url = self::optionalString($document, $name);
        if ($url !== null && !SecureUrl::isSecure($url)) {
            throw new DiscoveryException(sprintf('The discovery document\'s %s must be an absolute https URL (plain http is accepted for localhost, 127.0.0.1 and [::1] only) with %s.', $name, SecureUrl::UNAMBIGUOUS_RULE));
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function requiredString(array $document, string $name): string
    {
        return self::optionalString($document, $name) ?? throw self::missing($name);
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function optionalString(array $document, string $name): ?string
    {
        $value = $document[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new DiscoveryException(sprintf('The discovery document\'s %s must be a non-empty string.', $name));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $document
     * @return list<string>|null
     */
    private static function stringList(array $document, string $name): ?array
    {
        $value = $document[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new DiscoveryException(sprintf('The discovery document\'s %s must be a list of strings.', $name));
        }

        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new DiscoveryException(sprintf('The discovery document\'s %s must be a list of strings.', $name));
            }
            $strings[] = $item;
        }

        return $strings;
    }

    private static function missing(string $name): DiscoveryException
    {
        return new DiscoveryException(sprintf('The discovery document has no %s.', $name));
    }
}

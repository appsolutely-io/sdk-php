<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\MagicLinkToken;

/**
 * Passwordless sign-in for an app: the site emails a member a link, and the
 * link the app is opened with is exchanged for a member token. Both calls
 * carry no credential.
 */
final readonly class MagicLink
{
    /** @internal obtain it from Client::api()->magicLink() */
    public function __construct(private Caller $caller) {}

    /**
     * Asks the site to email the member a sign-in link. The site answers
     * the same whether or not the address belongs to a member.
     *
     * @param list<string> $abilities what the token the link buys may do, such as `me:read`
     * @param string $codeChallenge the PKCE S256 challenge of the verifier the exchange will send
     */
    #[Endpoint(Operation::RequestMagicLink)]
    public function request(string $email, array $abilities, string $deviceName, string $codeChallenge): void
    {
        $this->caller->send(Operation::RequestMagicLink, body: [
            'email' => $email,
            'abilities' => $abilities,
            'device_name' => $deviceName,
            'code_challenge' => $codeChallenge,
        ]);
    }

    /**
     * Exchanges the link the app was opened with for a member token.
     *
     * @param string $code the second-factor code, when the member has one
     * @param string $recoveryCode a recovery code, in place of the second-factor code
     * @param bool|null $lostSecondFactor true when the member reports their second factor lost
     */
    #[Endpoint(Operation::TokenMagicLink)]
    public function exchange(
        #[\SensitiveParameter]
        string $link,
        #[\SensitiveParameter]
        string $codeVerifier,
        #[\SensitiveParameter]
        ?string $code = null,
        #[\SensitiveParameter]
        ?string $recoveryCode = null,
        ?bool $lostSecondFactor = null,
    ): MagicLinkToken {
        $body = array_filter([
            'link' => $link,
            'code_verifier' => $codeVerifier,
            'code' => $code,
            'recovery_code' => $recoveryCode,
            'lost_second_factor' => $lostSecondFactor,
        ], static fn(mixed $value): bool => $value !== null);

        return MagicLinkToken::from(Fields::of($this->caller->object(Operation::TokenMagicLink, body: $body), MagicLinkToken::SCHEMA));
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Request\OAuth;

use Appsolutely\Sdk\Oidc\ClientAuthentication;

final readonly class ClientIdentity
{
    public function __construct(
        public string $clientId,
        #[\SensitiveParameter]
        private ?string $clientSecret = null,
        public ClientAuthentication $authentication = ClientAuthentication::ClientSecretBasic,
    ) {}

    /** @return array<string, string> */
    public function fields(): array
    {
        if ($this->clientSecret === null) {
            return ['client_id' => $this->clientId];
        }

        return $this->authentication === ClientAuthentication::ClientSecretPost
            ? ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret]
            : [];
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->clientSecret !== null && $this->authentication === ClientAuthentication::ClientSecretBasic
            ? ['Authorization' => 'Basic ' . base64_encode(urlencode($this->clientId) . ':' . urlencode($this->clientSecret))]
            : [];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => '[redacted]', 'authentication' => $this->authentication];
    }

    public function __serialize(): array
    {
        throw new \Appsolutely\Sdk\Exception\NotSerializableException('An OAuth client identity holds a secret and is not serialized.');
    }
}

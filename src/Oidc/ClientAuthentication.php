<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Oidc;

/**
 * How the client proves its identity to the token and revocation endpoints
 * (OpenID Connect Core section 9).
 */
enum ClientAuthentication: string
{
    case ClientSecretBasic = 'client_secret_basic';
    case ClientSecretPost = 'client_secret_post';
}

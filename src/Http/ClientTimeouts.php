<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Http;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Psr18Client as SymfonyClient;

/**
 * PSR-18 has no way to set a timeout, and the clients it finds wait as long
 * as their defaults say (Guzzle: forever). A client the SDK found for itself
 * is rebuilt with the Config's timeouts when it is one of the two common
 * ones (Symfony's from 6.2); any other is used as found, with a warning, since a hung connection
 * would otherwise hold a worker without a word.
 *
 * @internal used by Client when the Config passes no HTTP client
 */
final class ClientTimeouts
{
    public static function apply(ClientInterface $found, float $timeout, float $connectTimeout, LoggerInterface $logger): ClientInterface
    {
        if ($found instanceof GuzzleClient) {
            return new GuzzleClient(['timeout' => $timeout, 'connect_timeout' => $connectTimeout]);
        }

        // Symfony's idle timeout also bounds connecting; it has no separate
        // connect limit, so the request timeout serves as both. Psr18Client
        // takes options only from Symfony 6.2; an older one is used as found.
        // @phpstan-ignore function.alreadyNarrowedType (always true of the Symfony installed for analysis, 6.2 or later)
        if ($found instanceof SymfonyClient && method_exists($found, 'withOptions')) {
            return $found->withOptions(['timeout' => $timeout, 'max_duration' => $timeout]);
        }

        $logger->warning(sprintf(
            'The HTTP client found (%s) is not one the SDK can give a timeout to; a request may wait as long as that client allows. Pass Config::$httpClient built with a timeout.',
            $found::class,
        ));

        return $found;
    }
}

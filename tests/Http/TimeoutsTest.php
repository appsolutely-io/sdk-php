<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Client;
use Appsolutely\Sdk\Config;
use Appsolutely\Sdk\Exception\InvalidConfigException;
use Appsolutely\Sdk\Http\ClientTimeouts;
use Appsolutely\Sdk\Tests\Support\RecordingLogger;
use GuzzleHttp\Client as GuzzleClient;
use Http\Mock\Client as MockClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Psr18Client as SymfonyClient;

/**
 * PSR-18 has no portable timeout and Guzzle waits forever by default, so a
 * client the SDK finds for itself is built with a request and a connect
 * timeout; a client passed in is used as it is, its timeouts its owner's.
 */
final class TimeoutsTest extends TestCase
{
    public function testTheDefaultsAreThirtySecondsPerRequestAndTenToConnect(): void
    {
        $config = new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret');

        self::assertSame(30.0, $config->timeout);
        self::assertSame(10.0, $config->connectTimeout);
    }

    public function testAGuzzleClientIsBuiltWithBothTimeouts(): void
    {
        $client = ClientTimeouts::apply(new GuzzleClient(), 12.5, 4.0, new NullLogger());

        self::assertInstanceOf(GuzzleClient::class, $client);
        self::assertSame(12.5, self::guzzleOption($client, 'timeout'));
        self::assertSame(4.0, self::guzzleOption($client, 'connect_timeout'));
    }

    /**
     * Symfony HttpClient has no connect limit of its own: its idle timeout
     * covers connecting, and the request timeout caps the whole exchange.
     */
    public function testASymfonyClientIsBuiltWithTheRequestTimeout(): void
    {
        $client = ClientTimeouts::apply(new SymfonyClient(), 12.5, 4.0, new NullLogger());

        self::assertInstanceOf(SymfonyClient::class, $client);
        $options = self::symfonyOptions($client);
        self::assertSame(12.5, $options['timeout']);
        self::assertSame(12.5, $options['max_duration']);
    }

    /**
     * Psr18Client has had withOptions() only since Symfony 6.2. An older
     * one is run in its own process, as a stand-in of that name without the
     * method, since this process already loaded the installed one.
     */
    public function testASymfonyClientWithoutWithOptionsIsUsedAsFoundAndTheMissingTimeoutIsLogged(): void
    {
        $script = (string) tempnam(sys_get_temp_dir(), 'sdk-symfony-61-');
        file_put_contents($script, <<<'PHP'
            <?php
            namespace {
                require $argv[1];
            }
            namespace Symfony\Component\HttpClient {
                final class Psr18Client implements \Psr\Http\Client\ClientInterface
                {
                    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        throw new \LogicException('Not sent.');
                    }
                }
            }
            namespace {
                $logger = new class extends \Psr\Log\AbstractLogger {
                    public array $levels = [];
                    public function log($level, \Stringable|string $message, array $context = []): void
                    {
                        $this->levels[] = $level;
                    }
                };
                $found = new \Symfony\Component\HttpClient\Psr18Client();
                $client = \Appsolutely\Sdk\Http\ClientTimeouts::apply($found, 30.0, 10.0, $logger);
                echo json_encode(['same' => $client === $found, 'levels' => $logger->levels]);
            }
            PHP);

        try {
            $process = proc_open([PHP_BINARY, $script, dirname(__DIR__, 2) . '/vendor/autoload.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } finally {
            unlink($script);
        }

        self::assertSame(['same' => true, 'levels' => [LogLevel::WARNING]], json_decode($output, true), $output . $errors);
    }

    public function testAnotherClientIsUsedAsFoundAndTheMissingTimeoutIsLogged(): void
    {
        $logger = new RecordingLogger();
        $found = new MockClient();

        self::assertSame($found, ClientTimeouts::apply($found, 30.0, 10.0, $logger));
        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        self::assertStringContainsString('timeout', $logger->records[0]['message']);
    }

    public function testTheClientTheSdkFindsCarriesTheConfiguredTimeout(): void
    {
        $client = new Client(new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret', timeout: 7.0, connectTimeout: 2.0));

        $http = self::transportClient($client);
        if ($http instanceof GuzzleClient) {
            self::assertSame(7.0, self::guzzleOption($http, 'timeout'));
            self::assertSame(2.0, self::guzzleOption($http, 'connect_timeout'));
        } else {
            self::assertInstanceOf(SymfonyClient::class, $http);
            self::assertSame(7.0, self::symfonyOptions($http)['max_duration']);
        }
    }

    public function testAClientPassedInIsUsedAsItIs(): void
    {
        $passed = new GuzzleClient();
        $client = new Client(new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret', httpClient: $passed));

        self::assertSame($passed, self::transportClient($client));
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function refusedTimeouts(): iterable
    {
        yield 'no request timeout' => [0.0, 0.0];
        yield 'a negative request timeout' => [-1.0, 1.0];
        yield 'a request timeout beyond ten minutes' => [601.0, 10.0];
        yield 'no connect timeout' => [30.0, 0.0];
        yield 'a connect timeout beyond the request timeout' => [5.0, 10.0];
    }

    #[DataProvider('refusedTimeouts')]
    public function testATimeoutOutsideItsBoundsIsRefused(float $timeout, float $connectTimeout): void
    {
        $this->expectException(InvalidConfigException::class);

        new Config('https://site.example.com', 'https://login.example.com', 'id', 'secret', timeout: $timeout, connectTimeout: $connectTimeout);
    }

    private static function guzzleOption(GuzzleClient $client, string $name): mixed
    {
        $config = (new \ReflectionProperty(GuzzleClient::class, 'config'))->getValue($client);
        self::assertIsArray($config);

        return $config[$name] ?? null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function symfonyOptions(SymfonyClient $client): array
    {
        $inner = (new \ReflectionProperty(SymfonyClient::class, 'client'))->getValue($client);
        self::assertIsObject($inner);
        $options = (new \ReflectionProperty($inner, 'defaultOptions'))->getValue($inner);
        self::assertIsArray($options);

        return $options;
    }

    private static function transportClient(Client $client): ClientInterface
    {
        $site = (new \ReflectionProperty(Client::class, 'site'))->getValue($client);
        self::assertIsObject($site);
        $transport = (new \ReflectionProperty($site, 'http'))->getValue($site);
        self::assertIsObject($transport);
        $http = (new \ReflectionProperty($transport, 'client'))->getValue($transport);
        self::assertInstanceOf(ClientInterface::class, $http);

        return $http;
    }
}

<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A header field's name is written once, in Http\Header; the code that sends
 * or reads the field names the constant, so a misspelling cannot hide in one
 * of several copies.
 *
 * The source is read as PHP tokens, so only a whole string literal in the
 * place of a header name counts: a JSON member, an error message or a
 * comment that mentions a header is not one.
 *
 * @phpstan-import-type Token from SourceTokens
 */
final class HeaderTest extends TestCase
{
    /**
     * The calls that take a header name, by the arguments that may hold one:
     * PSR-7's take it first; `header()`, PHP's own or a class's helper, may
     * take it in any place.
     */
    private const array NAME_ARGUMENTS = [
        'withheader' => [0],
        'withaddedheader' => [0],
        'withoutheader' => [0],
        'getheader' => [0],
        'getheaderline' => [0],
        'hasheader' => [0],
        'header' => null,
    ];

    public function testNoStringLiteralSpellsTheNameOfAHeaderConstant(): void
    {
        $names = array_values((new ReflectionClass(Header::class))->getConstants());

        $found = [];
        foreach (self::sources() as $file => $tokens) {
            foreach ($tokens as [$id, $text, $line]) {
                if ($id === T_CONSTANT_ENCAPSED_STRING && in_array(self::unquote($text), $names, true)) {
                    $found[] = sprintf('%s:%d %s', $file, $line, $text);
                }
            }
        }

        self::assertSame([], $found);
    }

    public function testEveryHeaderNameACallTakesIsAHeaderConstant(): void
    {
        $found = [];
        foreach (self::sources() as $file => $tokens) {
            foreach (self::calledHeaderNames($tokens) as $name) {
                $found[] = sprintf('%s:%d %s', $file, $name[2], $name[1]);
            }
        }

        self::assertSame([], $found);
    }

    /**
     * A header array is an array literal with a Header constant among its
     * keys, or one assigned or passed as `headers`; its other keys, and a
     * `$headers[...]` offset, are header names too.
     */
    public function testEveryKeyOfAHeaderArrayIsAHeaderConstant(): void
    {
        $found = [];
        foreach (self::sources() as $file => $tokens) {
            foreach (self::headerArrayKeys($tokens) as $key) {
                $found[] = sprintf('%s:%d %s', $file, $key[2], $key[1]);
            }
        }

        self::assertSame([], $found);
    }

    public function testTheCallRuleSeesTheHeaderNameInEachPlaceACallTakesOne(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            $request->withHeader('X-Trace', 'v')->getHeaderLine("Accept");
            $this->header($all, 'webhook-id');
            $response->withHeader(Header::ACCEPT, 'application/json')->withoutHeader($name);
            $cache->get('X-Not-A-Header');
            function header(string $name = 'X-Default', string ...$values): void {}
            PHP);

        self::assertSame(["'X-Trace'", '"Accept"', "'webhook-id'"], array_map(static fn(array $name): string => $name[1], self::calledHeaderNames($tokens)));
    }

    public function testTheArrayRuleSeesTheKeysOfEveryHeaderArray(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            $headers = ['webhook-id' => $id];
            $map = [Header::AUTHORIZATION => $token, 'X-Other' => 'v'];
            $headers['X-Late'] = 'v';
            send(headers: ['X-Named' => 'v']);
            new SignedWebhook($id, $body, ['webhook-timestamp' => $time]);
            $cache->get('key', ['X-Not-A-Header' => 'v']);
            $transport->postForm($url, ['X-Not-A-Header' => 'v'], ['X-Form' => 'v']);
            $json = ['location' => 'here', 'P-256' => $curve, 'validation-failed' => 1];
            PHP);

        self::assertSame(["'webhook-id'", "'X-Other'", "'X-Late'", "'X-Named'", "'webhook-timestamp'", "'X-Form'"], array_map(static fn(array $key): string => $key[1], self::headerArrayKeys($tokens)));
    }

    /**
     * Every source file but Header's, as tokens.
     *
     * @return iterable<string, list<Token>>
     */
    private static function sources(): iterable
    {
        foreach (SourceTokens::sources() as $file => $tokens) {
            if ($file !== 'Http/Header.php') {
                yield $file => $tokens;
            }
        }
    }

    /**
     * The string literals in the places of header names in calls that take
     * one.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function calledHeaderNames(array $tokens): array
    {
        $found = [];
        foreach ($tokens as $index => [$id, $text]) {
            $method = strtolower($text);
            if ($id !== T_STRING || !array_key_exists($method, self::NAME_ARGUMENTS) || ($tokens[$index + 1][0] ?? null) !== '(' || ($tokens[$index - 1][0] ?? null) === T_FUNCTION) {
                continue;
            }
            $positions = self::NAME_ARGUMENTS[$method];
            foreach (SourceTokens::arguments($tokens, $index + 1) as $position => $argument) {
                if (($positions === null || in_array($position, $positions, true)) && SourceTokens::isLiteral($argument)) {
                    $found[] = $argument[0];
                }
            }
        }

        return $found;
    }

    /**
     * The string-literal keys of every header array, and the string-literal
     * offsets of `$headers[...]`.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function headerArrayKeys(array $tokens): array
    {
        $parameters = self::headerParameters();
        $found = [];
        // One frame per open bracket: whether it opens an array literal and
        // is a header array, its literal keys, the call it is the argument
        // list of and the argument reached, and the element it interrupted.
        $frames = [];
        $element = [];
        foreach ($tokens as $index => $token) {
            $id = $token[0];
            $before = $tokens[$index - 1][0] ?? null;
            if (in_array($id, SourceTokens::OPENERS, true)) {
                $isArray = ($id === '[' && !in_array($before, [T_VARIABLE, ']', ')', '}', T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_CONSTANT_ENCAPSED_STRING], true))
                    || ($id === '(' && $before === T_ARRAY);
                $parent = $frames === [] ? null : $frames[array_key_last($frames)];
                $isArgument = $parent !== null && $element === [] && in_array($parent['position'], $parameters[$parent['callee']] ?? [], true);
                if ($id === '[' && $before === T_VARIABLE && preg_match('/headers$/i', $tokens[$index - 1][1]) === 1) {
                    $offset = SourceTokens::arguments($tokens, $index)[0];
                    if (SourceTokens::isLiteral($offset)) {
                        $found[] = $offset[0];
                    }
                }
                $frames[] = [
                    'array' => $isArray,
                    'headers' => $isArray && (self::isNamedHeaders($tokens, $index) || $isArgument),
                    'keys' => [],
                    'callee' => $id === '(' ? self::callee($tokens, $index) : '',
                    'position' => 0,
                    'element' => $element,
                ];
                $element = [];

                continue;
            }
            if ($frames !== [] && in_array($id, SourceTokens::CLOSERS, true)) {
                $frame = array_pop($frames);
                if ($frame['headers']) {
                    $found = [...$found, ...$frame['keys']];
                }
                $element = [...$frame['element'], $token];

                continue;
            }
            $top = array_key_last($frames);
            if ($top !== null && $id === ',') {
                $frames[$top]['position']++;
                $element = [];

                continue;
            }
            if ($top !== null && $frames[$top]['array'] && $id === T_DOUBLE_ARROW && !in_array(T_DOUBLE_ARROW, array_column($element, 0), true)) {
                if (SourceTokens::isLiteral($element)) {
                    $frames[$top]['keys'][] = $element[0];
                } elseif (count($element) === 3 && $element[0][1] === 'Header' && $element[1][0] === T_DOUBLE_COLON) {
                    $frames[$top]['headers'] = true;
                }
            }
            $element[] = $token;
        }

        return $found;
    }

    /**
     * The name of the call whose argument list opens at $index, lower case,
     * as `new <class>` for a constructor; empty when it is no call.
     *
     * @param list<Token> $tokens
     */
    private static function callee(array $tokens, int $index): string
    {
        $name = $tokens[$index - 1] ?? null;
        if ($name === null || !in_array($name[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return '';
        }
        $short = strtolower(substr((string) strrchr('\\' . $name[1], '\\'), 1));

        return ($tokens[$index - 2][0] ?? null) === T_NEW ? 'new ' . $short : $short;
    }

    /**
     * The places of the parameters named `headers` (or ending so) of the
     * methods in src/, by method name in lower case, or `new <class>` for a
     * constructor: an array literal written there is a header array. A call
     * is matched by name alone, so a place counts only when every method of
     * that name has a header parameter there; `get()` does not, as a cache's
     * `get()` takes a default value in that place.
     *
     * @return array<string, list<int>>
     */
    private static function headerParameters(): array
    {
        $places = [];
        foreach (SourceTokens::classNames() as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $name = $method->isConstructor() ? 'new ' . strtolower($reflection->getShortName()) : strtolower($method->getName());
                $own = [];
                foreach ($method->getParameters() as $parameter) {
                    if (preg_match('/headers$/i', $parameter->getName()) === 1 && (string) $parameter->getType() === 'array') {
                        $own[] = $parameter->getPosition();
                    }
                }
                $places[$name] = array_key_exists($name, $places) ? array_values(array_intersect($places[$name], $own)) : $own;
            }
        }

        return array_filter($places, static fn(array $positions): bool => $positions !== []);
    }

    /**
     * Whether the bracket at $index opens what is assigned to `$headers`
     * (or a variable ending so) or passed as the named argument `headers`.
     *
     * @param list<Token> $tokens
     */
    private static function isNamedHeaders(array $tokens, int $index): bool
    {
        $operator = $tokens[$index - 1] ?? null;
        $name = $tokens[$index - 2] ?? null;
        if ($operator === null || $name === null) {
            return false;
        }

        return ($operator[0] === '=' && $name[0] === T_VARIABLE && preg_match('/headers$/i', $name[1]) === 1)
            || ($operator[0] === ':' && $name[0] === T_STRING && $name[1] === 'headers');
    }

    private static function unquote(string $literal): string
    {
        return substr($literal, 1, -1);
    }
}

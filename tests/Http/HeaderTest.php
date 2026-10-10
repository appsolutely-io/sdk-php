<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Http;

use Appsolutely\Sdk\Http\Header;
use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Tests\Support\SourceTokens;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A header field's name is written once, in Http\Header; the code that sends
 * or reads the field names the constant, so a misspelling cannot hide in one
 * of several copies. Header names are compared in any case, as HTTP does.
 *
 * The source is read as PHP tokens, so only a whole string literal counts:
 * an error message or a comment that mentions a header is not one, and a
 * JSON member named like a header (`location`) is not one either.
 *
 * Out of reach: a header array built away from where it is used and held
 * in a variable that is neither named `...headers` nor assigned an array
 * literal with a Header constant among its keys; the left operand of
 * `[...] + $headers` and the arrays handed to array_merge(); a call whose
 * method is named by a variable. A call on a receiver whose type the same
 * file does not declare is matched against every method of that name, so
 * it can only be refused too often, never too rarely.
 *
 * @phpstan-import-type Token from SourceTokens
 * @phpstan-type Parameters array{byClass: array<string, array<string, list<int>>>, byMethod: array<string, list<int>>}
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

    /** A variable or a property that holds a header array by its name. */
    private const string HEADERS_NAME = '/headers$/i';

    /** The tokens a class, method or type is named by. */
    private const array NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    public function testNoStringLiteralSpellsAHeaderName(): void
    {
        $found = [];
        foreach (self::sources() as $file => $tokens) {
            foreach (self::spelledHeaderNames($tokens) as $literal) {
                $found[] = sprintf('%s:%d %s', $file, $literal[2], $literal[1]);
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
     * keys, one assigned to a variable or a property named `...headers`,
     * one passed as an argument named so (by place or by name), or one
     * added to such a variable with `+`; its other keys, and a literal
     * offset of a variable or property that holds one, are header names
     * too.
     */
    public function testEveryKeyOfAHeaderArrayIsAHeaderConstant(): void
    {
        $parameters = self::headerParameters();
        $found = [];
        foreach (self::sources() as $file => $tokens) {
            foreach (self::headerArrayKeys($tokens, $parameters) as $key) {
                $found[] = sprintf('%s:%d %s', $file, $key[2], $key[1]);
            }
        }

        self::assertSame([], $found);
    }

    public function testTheLiteralRuleSeesAHeaderNameInAnyCaseButNotAJsonMember(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            $type = 'Content-Type';
            $late = strtolower($name) === 'retry-after';
            $where = $json->nullableString('location');
            $rest = $json->except('accept', 'id');
            $member = ['accept' => 1, 'Location' => 2];
            $read = $data['location'];
            $near = 'locations';
            PHP);

        self::assertSame(["'Content-Type'", "'retry-after'", "'Location'"], array_map(static fn(array $literal): string => $literal[1], self::spelledHeaderNames($tokens)));
    }

    public function testTheCallRuleSeesTheHeaderNameInEachPlaceACallTakesOne(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            $request->withHeader('X-Trace', 'v')->getHeaderLine("Accept");
            $this->header($all, 'webhook-id');
            $response->withHeader(Header::ACCEPT, 'application/json')->withoutHeader($name);
            $request->withAddedHeader(value: 'v', name: 'X-By-Name');
            $cache->get('X-Not-A-Header');
            function header(string $name = 'X-Default', string ...$values): void {}
            PHP);

        self::assertSame(["'X-Trace'", '"Accept"', "'webhook-id'", "'X-By-Name'"], array_map(static fn(array $name): string => $name[1], self::calledHeaderNames($tokens)));
    }

    public function testTheArrayRuleSeesTheKeysOfEveryHeaderArray(): void
    {
        $tokens = SourceTokens::of(<<<'PHP'
            <?php
            final class Sample
            {
                public function __construct(private HttpTransport $http, private InMemoryCache $cache) {}

                public function run(Verifier $verifier, $unknown): void
                {
                    $headers = ['webhook-id' => $id];
                    $headers['X-Late'] = 'v';
                    $this->headers["X-Property"] = 'v';
                    $map = [Header::AUTHORIZATION => $token, 'X-Other' => 'v'];
                    $map['X-Map'] = 'v';
                    $more = $headers + ['X-Added' => 'v'];
                    $this->http->get($url, ['X-Get' => 'v']);
                    $verifier->verify($body, ['X-Verify' => 'v']);
                    $unknown->answer($operation, null, 200, ['X-Answer' => 'v']);
                    send(extraHeaders: ['X-Named' => 'v']);
                    new SignedWebhook($id, $body, ['webhook-timestamp' => $time]);
                    $this->http->postForm($url, ['X-Form-Field' => 'v'], ['X-Form' => 'v']);
                    $this->cache->get('key', ['X-Not-A-Header' => 'v']);
                    $json = ['location' => 'here', 'P-256' => $curve, 'validation-failed' => 1];
                    $json['Accept'] = 1;
                }
            }
            PHP);

        self::assertSame(
            ["'webhook-id'", "'X-Late'", '"X-Property"', "'X-Other'", "'X-Map'", "'X-Added'", "'X-Get'", "'X-Verify'", "'X-Answer'", "'X-Named'", "'webhook-timestamp'", "'X-Form'"],
            array_map(static fn(array $key): string => $key[1], self::headerArrayKeys($tokens, self::headerParameters())),
        );
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
     * The string literals that spell the name of a Header constant: in its
     * own case anywhere, in another case anywhere but as a JSON member,
     * which is an array key, an offset or a name handed to a Fields reader.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function spelledHeaderNames(array $tokens): array
    {
        $names = array_values(array_filter((new ReflectionClass(Header::class))->getConstants(), is_string(...)));
        $lowerNames = array_map(strtolower(...), $names);
        $members = self::jsonMemberNames($tokens);
        $found = [];
        foreach ($tokens as $index => $token) {
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $text = substr($token[1], 1, -1);
            $isMember = in_array($index, $members, true)
                || ($tokens[$index + 1][0] ?? null) === T_DOUBLE_ARROW
                || (($tokens[$index - 1][0] ?? null) === '[' && ($tokens[$index + 1][0] ?? null) === ']');
            if (in_array($text, $names, true) || (!$isMember && in_array(strtolower($text), $lowerNames, true))) {
                $found[] = $token;
            }
        }

        return $found;
    }

    /**
     * The places of the string literals handed to a Fields reader as the
     * name of a member, such as `$json->string('location')`.
     *
     * @param list<Token> $tokens
     * @return list<int>
     */
    private static function jsonMemberNames(array $tokens): array
    {
        $readers = [];
        foreach ((new ReflectionClass(Fields::class))->getMethods() as $method) {
            $first = $method->getParameters()[0] ?? null;
            if (!$method->isStatic() && $first !== null && in_array($first->getName(), ['name', 'names'], true)) {
                $readers[] = strtolower($method->getName());
            }
        }

        $places = [];
        foreach ($tokens as $index => [$id, $text]) {
            if ($id !== T_STRING || !in_array(strtolower($text), $readers, true) || !in_array($tokens[$index - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true) || ($tokens[$index + 1][0] ?? null) !== '(') {
                continue;
            }
            for ($at = $index + 2; ($tokens[$at][0] ?? ')') !== ')'; $at += 2) {
                if ($tokens[$at][0] === T_CONSTANT_ENCAPSED_STRING && in_array($tokens[$at + 1][0] ?? null, [',', ')'], true)) {
                    $places[] = $at;
                }
                if (($tokens[$at + 1][0] ?? null) !== ',') {
                    break;
                }
            }
        }

        return $places;
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
                // A named argument: PSR-7 calls the header name `name`.
                $named = count($argument) > 2 && $argument[0][0] === T_STRING && $argument[1][0] === ':' ? $argument[0][1] : null;
                $value = $named === null ? $argument : array_slice($argument, 2);
                $inPlace = $positions === null || ($named === null ? in_array($position, $positions, true) : $named === 'name');
                if ($inPlace && SourceTokens::isLiteral($value)) {
                    $found[] = $value[0];
                }
            }
        }

        return $found;
    }

    /**
     * The string-literal keys of every header array, and the string-literal
     * offsets of what holds one.
     *
     * @param list<Token> $tokens
     * @param Parameters $parameters
     * @return list<Token>
     */
    private static function headerArrayKeys(array $tokens, array $parameters): array
    {
        $class = self::declaredClass($tokens);
        $types = self::declaredTypes($tokens);
        // What holds a header array, by `$name` or `->name`.
        $holders = [];
        $found = [];
        // One frame per open bracket: whether it opens an array literal and
        // is a header array, its literal keys, what it is assigned to, the
        // header places of the call it is the argument list of and the
        // argument reached, and the element it interrupted.
        $frames = [];
        $element = [];
        foreach ($tokens as $index => $token) {
            $id = $token[0];
            $before = $tokens[$index - 1][0] ?? null;
            if (in_array($id, SourceTokens::OPENERS, true)) {
                $isArray = ($id === '[' && !in_array($before, [T_VARIABLE, ']', ')', '}', T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_CONSTANT_ENCAPSED_STRING], true))
                    || ($id === '(' && $before === T_ARRAY);
                $parent = $frames === [] ? null : $frames[array_key_last($frames)];
                $isArgument = $parent !== null && $element === [] && in_array($parent['position'], $parent['places'], true);
                $holder = self::holder($tokens, $index - 1);
                if ($id === '[' && $holder !== null && (preg_match(self::HEADERS_NAME, $holder) === 1 || in_array($holder, $holders, true))) {
                    $offset = SourceTokens::arguments($tokens, $index)[0];
                    if (SourceTokens::isLiteral($offset)) {
                        $found[] = $offset[0];
                    }
                }
                $assignedTo = $before === '=' ? self::holder($tokens, $index - 2) : null;
                $addedTo = $before === '+' ? self::holder($tokens, $index - 2) : null;
                $frames[] = [
                    'array' => $isArray,
                    'headers' => $isArray && (
                        $isArgument
                        || self::isNamedArgument($tokens, $index)
                        || ($assignedTo !== null && preg_match(self::HEADERS_NAME, $assignedTo) === 1)
                        || ($addedTo !== null && (preg_match(self::HEADERS_NAME, $addedTo) === 1 || in_array($addedTo, $holders, true)))
                    ),
                    'keys' => [],
                    'assignedTo' => $assignedTo,
                    'places' => $id === '(' ? self::headerPlaces($tokens, $index, $class, $types, $parameters) : [],
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
                    if ($frame['assignedTo'] !== null) {
                        $holders[] = $frame['assignedTo'];
                    }
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
     * What the token at $index names as a holder of a value: `$name` for a
     * variable, `->name` for a property; null for anything else.
     *
     * @param list<Token> $tokens
     */
    private static function holder(array $tokens, int $index): ?string
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }
        if ($token[0] === T_VARIABLE) {
            return $token[1];
        }
        if ($token[0] === T_STRING && in_array($tokens[$index - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
            return '->' . $token[1];
        }

        return null;
    }

    /**
     * Whether the bracket at $index opens a named argument called
     * `...headers`.
     *
     * @param list<Token> $tokens
     */
    private static function isNamedArgument(array $tokens, int $index): bool
    {
        $name = $tokens[$index - 2] ?? null;

        return ($tokens[$index - 1][0] ?? null) === ':'
            && $name !== null && $name[0] === T_STRING && preg_match(self::HEADERS_NAME, $name[1]) === 1
            && in_array($tokens[$index - 3][0] ?? null, ['(', ','], true);
    }

    /**
     * The places of the header parameters of the call whose argument list
     * opens at $index: of the method the call reaches when the receiver's
     * class is known (a constructor, a static call, `$this`, or a variable
     * or property whose type the file declares), else of every method of
     * that name; empty when it is no call but a declaration.
     *
     * @param list<Token> $tokens
     * @param array<string, string> $types
     * @param Parameters $parameters
     * @return list<int>
     */
    private static function headerPlaces(array $tokens, int $index, ?string $class, array $types, array $parameters): array
    {
        $name = $tokens[$index - 1] ?? null;
        if ($name === null || !in_array($name[0], self::NAMES, true) || ($tokens[$index - 2][0] ?? null) === T_FUNCTION) {
            return [];
        }
        $method = strtolower(self::shortName($name[1]));
        $operator = $tokens[$index - 2][0] ?? null;
        $receiver = $tokens[$index - 3] ?? null;
        $owner = match (true) {
            $operator === T_NEW => [$method, '__construct'],
            $operator === T_DOUBLE_COLON && $receiver !== null && in_array(strtolower($receiver[1]), ['self', 'static'], true) => [$class, $method],
            $operator === T_DOUBLE_COLON && $receiver !== null && in_array($receiver[0], self::NAMES, true) && strtolower($receiver[1]) !== 'parent' => [strtolower(self::shortName($receiver[1])), $method],
            in_array($operator, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true) && $receiver !== null && $receiver[1] === '$this' => [$class, $method],
            in_array($operator, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true) => [$types[self::holder($tokens, $index - 3) ?? ''] ?? null, $method],
            default => [null, $method],
        };
        [$ownerClass, $ownerMethod] = $owner;

        return $ownerClass !== null && isset($parameters['byClass'][$ownerClass])
            ? $parameters['byClass'][$ownerClass][$ownerMethod] ?? []
            : $parameters['byMethod'][$ownerMethod] ?? [];
    }

    /**
     * The short name of the first class, interface, trait or enum the
     * tokens declare, in lower case.
     *
     * @param list<Token> $tokens
     */
    private static function declaredClass(array $tokens): ?string
    {
        foreach ($tokens as $index => [$id]) {
            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && ($tokens[$index + 1][0] ?? null) === T_STRING && !in_array($tokens[$index - 1][0] ?? null, [T_DOUBLE_COLON, T_NEW], true)) {
                return strtolower($tokens[$index + 1][1]);
            }
        }

        return null;
    }

    /**
     * The class each typed variable, parameter or property the tokens
     * declare is of, short and in lower case, by `$name` and `->name`: a
     * promoted or declared property is reached as both.
     *
     * @param list<Token> $tokens
     * @return array<string, string>
     */
    private static function declaredTypes(array $tokens): array
    {
        $types = [];
        foreach ($tokens as $index => [$id, $text]) {
            $type = $tokens[$index - 1] ?? null;
            if ($id === T_VARIABLE && $type !== null && in_array($type[0], self::NAMES, true)) {
                $short = strtolower(self::shortName($type[1]));
                $types[$text] = $short;
                $types['->' . substr($text, 1)] = $short;
            }
        }

        return $types;
    }

    /**
     * The places of the parameters named `...headers` and typed array, by
     * method in lower case (`__construct` for a constructor): by class,
     * short and in lower case, with what each inherits; and by method name
     * alone, for every class that declares one.
     *
     * @return Parameters
     */
    private static function headerParameters(): array
    {
        $byClass = [];
        $byMethod = [];
        foreach (SourceTokens::classNames() as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            $short = strtolower($reflection->getShortName());
            foreach ($reflection->getMethods() as $method) {
                $name = strtolower($method->getName());
                $own = [];
                foreach ($method->getParameters() as $parameter) {
                    if (preg_match(self::HEADERS_NAME, $parameter->getName()) === 1 && (string) $parameter->getType() === 'array') {
                        $own[] = $parameter->getPosition();
                    }
                }
                $byClass[$short][$name] = array_values(array_unique([...$byClass[$short][$name] ?? [], ...$own]));
                if ($method->getDeclaringClass()->getName() === $class) {
                    $byMethod[$name] = array_values(array_unique([...$byMethod[$name] ?? [], ...$own]));
                }
            }
        }

        return ['byClass' => $byClass, 'byMethod' => $byMethod];
    }

    private static function shortName(string $name): string
    {
        return substr((string) strrchr('\\' . $name, '\\'), 1);
    }
}

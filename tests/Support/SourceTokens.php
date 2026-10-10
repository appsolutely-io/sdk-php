<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The package's source read as PHP tokens, for tests that hold a rule over
 * how the code is written: whitespace and comments dropped, every token a
 * triple of its id (a T_* constant, or the character itself), its text and
 * its line.
 *
 * @phpstan-type Token array{int|string, string, int}
 */
final class SourceTokens
{
    /** The tokens that open a bracket a matching `)`, `]` or `}` closes. */
    public const array OPENERS = ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE];

    public const array CLOSERS = [')', ']', '}'];

    /**
     * Every PHP file under src/, as tokens, by its path under src/.
     *
     * @return iterable<string, list<Token>>
     */
    public static function sources(): iterable
    {
        $root = dirname(__DIR__, 2) . '/src';

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                yield substr($file->getPathname(), strlen($root) + 1) => self::of((string) file_get_contents($file->getPathname()));
            }
        }
    }

    /**
     * @return list<Token>
     */
    public static function of(string $source): array
    {
        $tokens = [];
        $line = 1;
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                $line = $token[2];
                if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                    $tokens[] = [$token[0], $token[1], $line];
                }
            } else {
                $tokens[] = [$token, $token, $line];
            }
        }

        return $tokens;
    }

    /**
     * What sits between the bracket opened at $open and the one closing it,
     * split at its top-level commas: a call's arguments, a declaration's
     * parameters, an offset.
     *
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    public static function arguments(array $tokens, int $open): array
    {
        $arguments = [[]];
        $depth = 0;
        for ($index = $open + 1; $index < count($tokens); $index++) {
            $id = $tokens[$index][0];
            if (in_array($id, self::OPENERS, true)) {
                $depth++;
            } elseif (in_array($id, self::CLOSERS, true)) {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($id === ',' && $depth === 0) {
                $arguments[] = [];
                continue;
            }
            $arguments[array_key_last($arguments)][] = $tokens[$index];
        }

        return $arguments;
    }

    /**
     * Whether the tokens are one string literal and nothing else.
     *
     * @param list<Token> $tokens
     * @phpstan-assert-if-true non-empty-list<Token> $tokens
     */
    public static function isLiteral(array $tokens): bool
    {
        return count($tokens) === 1 && $tokens[0][0] === T_CONSTANT_ENCAPSED_STRING;
    }
}

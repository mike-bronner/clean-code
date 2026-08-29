<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags private methods and private properties that are never referenced
 * inside their declaring class-like body — dead code that must be removed.
 *
 * Replaces SlevomatCodingStandard.Classes.UnusedPrivateElements, which was
 * removed from slevomat/coding-standard in 7.0. Detection is deliberately
 * conservative to avoid false positives: any mention of an element's name in
 * the body — property/method access, static access, or a string literal
 * (callable arrays, compact(), interpolation) — counts as a usage. Dynamic
 * access via variable variables cannot be detected and stays with code review.
 *
 * Which class-like constructs are scanned, and why:
 *
 * - `T_CLASS`, `T_ENUM`, `T_ANON_CLASS` are all scanned. A private member of
 *   any of the three is reachable only from that same body, so a single-file
 *   token scan can prove it dead. (An enum declares no properties — PHP
 *   forbids enum state — so only its methods can ever be reported.)
 * - `T_TRAIT` is deliberately not scanned. A trait's private member is
 *   flattened into every consuming class and may be used only there, so the
 *   trait's own body does not prove it dead.
 * - `T_INTERFACE` is not scanned because PHP forbids private interface
 *   members outright.
 *
 * Each body is measured against its own mentions only. A nested anonymous
 * class gets its own pass, and its mentions stay there: PHP denies it access
 * to the enclosing class's private members, so they can never be a usage of
 * one. Its constructor arguments are a different matter — they are evaluated
 * in the enclosing scope and still count there.
 *
 * Methods and properties are matched against separate usage maps, because PHP
 * keeps them in separate namespaces: `$this->foo` reads a property and
 * `$this->foo()` calls a method, and one must not mark the other used. Names
 * mined from string literals stay ambiguous — a bare `'foo'` could name
 * either — so they mark both.
 */
class UnusedPrivateElementsSniff implements Sniff
{
    /**
     * Tokens that terminate the statement a class-member declaration sits in,
     * used when scanning backwards for its visibility modifier.
     */
    private const STATEMENT_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * Object/static access tokens that mark the following name as a usage.
     */
    private const ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * String tokens whose contents are mined for usage mentions.
     */
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS, T_ENUM, T_ANON_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        [$properties, $methods] = $this->findPrivateDeclarations($phpcsFile, $opener, $closer);

        if ($properties === [] && $methods === []) {
            return;
        }

        [$usedProperties, $usedMethods] = $this->collectUsedNames($phpcsFile, $opener, $closer);

        foreach ($properties as $name => $declarationPtr) {
            if (isset($usedProperties[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private property $%s must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedProperty',
                    [ltrim($tokens[$declarationPtr]['content'], '$')]
                );
            }
        }

        foreach ($methods as $name => $declarationPtr) {
            if (isset($usedMethods[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private method %s() must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedMethod',
                    [$tokens[$declarationPtr]['content']]
                );
            }
        }
    }

    /**
     * Collects private property and method declarations at body level.
     * Method bodies (and parameter lists, which excludes promoted constructor
     * properties) are jumped over. Magic methods are never collected — they
     * are invoked implicitly.
     *
     * A nested anonymous class body is jumped over here rather than ignored:
     * register() lists T_ANON_CLASS, so process() reaches that body on its own
     * pass, against its own usage maps. Collecting its members here instead
     * would test them against the enclosing body's usages, which cannot see
     * them.
     *
     * @return array{0: array<string, int>, 1: array<string, int>} lowercased
     *         name (property names without the $) => declaration name pointer
     */
    private function findPrivateDeclarations(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $properties = [];
        $methods = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_FUNCTION) {
                $namePtr = $phpcsFile->findNext(T_STRING, ($i + 1));

                if ($namePtr !== false && $this->isPrivate($phpcsFile, $i) === true) {
                    $name = strtolower($tokens[$namePtr]['content']);

                    if (str_starts_with($name, '__') === false) {
                        $methods[$name] = $namePtr;
                    }
                }

                $i = $tokens[$i]['scope_closer'] ?? $tokens[$i]['parenthesis_closer'] ?? $i;

                continue;
            }

            if ($tokens[$i]['code'] === T_ANON_CLASS && isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];

                continue;
            }

            if ($tokens[$i]['code'] === T_VARIABLE && $this->isPrivate($phpcsFile, $i) === true) {
                $properties[strtolower(ltrim($tokens[$i]['content'], '$'))] = $i;
            }
        }

        return [$properties, $methods];
    }

    /**
     * Whether the member declaration at $stackPtr carries a private modifier,
     * found by scanning back to the start of its statement.
     */
    private function isPrivate(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if (in_array($tokens[$i]['code'], self::STATEMENT_BOUNDARIES, true) === true) {
                return false;
            }

            if ($tokens[$i]['code'] === T_PRIVATE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collects every name mentioned in the body that could reference a private
     * element: names after `->`, `?->`, or `::`, static property variables
     * after `::`, and every word inside string literals (covering callable
     * arrays, compact(), and interpolation). All lowercased.
     *
     * An access-operator mention is attributed to exactly one of the two maps,
     * decided by whether a `(` follows the name: `$this->foo()` calls the
     * method and says nothing about a same-named property, `$this->foo` reads
     * the property and says nothing about a same-named method. PHP keeps the
     * two in separate namespaces, so a shared map would let either mention
     * mark both used and hide the other as dead code.
     *
     * A word mined from a string literal cannot be attributed that way — a
     * bare `'foo'` is as plausibly a callable-array method name as a
     * `compact()` property name — so it marks both. That ambiguity is a
     * deliberate false negative, in keeping with the sniff's conservative
     * "any mention counts" heuristic.
     *
     * A nested anonymous class body is jumped over, mirroring the skip
     * findPrivateDeclarations() makes: its mentions belong to that body, not
     * to this one, and counting them here would mark an enclosing member used
     * that nothing in the enclosing body ever touches.
     *
     * @return array{0: array<string, true>, 1: array<string, true>} used
     *         property names, then used method names
     */
    private function collectUsedNames(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $usedProperties = [];
        $usedMethods = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            if ($this->opensAnonymousClass($phpcsFile, $i) === true) {
                $i = $tokens[$i]['scope_closer'];

                continue;
            }

            if ($code === T_STRING || $code === T_VARIABLE) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($i - 1), null, true);

                if ($prev !== false && in_array($tokens[$prev]['code'], self::ACCESS_OPERATORS, true) === true) {
                    $name = strtolower(ltrim($tokens[$i]['content'], '$'));

                    if ($this->isCall($phpcsFile, $i) === true) {
                        $usedMethods[$name] = true;
                    } else {
                        $usedProperties[$name] = true;
                    }
                }

                continue;
            }

            if (in_array($code, self::STRING_TOKENS, true) === true) {
                $matched = preg_match_all(
                    '/[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/',
                    $tokens[$i]['content'],
                    $matches
                );

                // A failed read leaves $matches without its [0] key, and the
                // foreach below then throws a TypeError that takes the run
                // down. Skipping the token sends the failure the way the rest
                // of this walk already leans: a word this read did not collect
                // is a word no element is proven to use, so a used element can
                // be reported as unused — a report to argue with, not a
                // silence to miss. The pattern is two character classes, the
                // second auto-possessified at the end of the pattern, with no
                // `/u` modifier, so nothing is known to reach the branch.
                if ($matched === false) {
                    continue;
                }

                foreach ($matches[0] as $word) {
                    $usedProperties[strtolower($word)] = true;
                    $usedMethods[strtolower($word)] = true;
                }
            }
        }

        return [$usedProperties, $usedMethods];
    }

    /**
     * Whether $stackPtr is the brace that opens an anonymous class body.
     *
     * An anonymous class is a separate class, and PHP refuses it access to the
     * enclosing class's private members — `$outer->helper()` inside one raises
     * "Call to private method Outer::helper() from scope class@anonymous". So
     * a mention inside that body can never be a usage of an enclosing private
     * member, and must not mark one used.
     *
     * The body is skipped from its opening brace rather than from the
     * T_ANON_CLASS token, because the constructor arguments in between belong
     * to the enclosing body: `new class ($this->config)` reads an enclosing
     * property and is a real usage.
     *
     * Anonymous classes are the only class-like construct this has to handle.
     * PHP rejects a named class, enum, trait, or interface declared inside
     * another class-like body — or inside a closure within one — with "Class
     * declarations may not be nested".
     */
    private function opensAnonymousClass(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if (isset($token['scope_condition'], $token['scope_opener'], $token['scope_closer']) === false) {
            return false;
        }

        return $token['scope_opener'] === $stackPtr
            && $tokens[$token['scope_condition']]['code'] === T_ANON_CLASS;
    }

    /**
     * Whether the accessed name at $stackPtr is immediately invoked, which is
     * what separates a method call from a property read.
     */
    private function isCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        return $next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS;
    }
}

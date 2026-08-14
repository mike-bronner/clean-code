<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags Reflection-based access to non-public members inside test files.
 *
 * Testing: Guidelines (#54) — docs/standards/testing-guidelines.md — says to
 * only test public methods and to cover protected/private ones through them.
 * The semantic core of that is unenforceable, but one slice is token-visible:
 * the standard PHP workaround for reaching a non-public member from a test is
 * Reflection. This sniff catches that mechanism (#145).
 *
 * Two independent detections, each driven by its own public property so a
 * consuming ruleset can retune either without touching the other:
 *
 * - `new ReflectionMethod(...)` / `new ReflectionProperty(...)` —
 *   $reflectionClasses. Both classes exist to address one named member, so
 *   naming either in a test is already the workaround.
 * - `setAccessible()`, `getMethod()`, `getProperty()`, `invoke()`,
 *   `invokeArgs()` — $reflectionMembers. Matched on `->`, `?->` and `::`
 *   alike, so `$reflection->getMethod('x')` and the `ReflectionClass::getMethod()`
 *   spelling both report.
 *
 * `new ReflectionClass(...)` is deliberately NOT in the default class list.
 * Reading a class's metadata is legitimate (data providers, framework
 * plumbing); it only becomes this standard's violation once `getMethod()` or
 * `getProperty()` narrows it to one member, which $reflectionMembers catches.
 *
 * Scope: the sniff inspects a file only when its path matches one of
 * $testFilePatterns (fnmatch globs, defaulting to any path holding a `tests`
 * or `Tests` directory segment, plus any filename ending `Test.php`).
 * Production code is never inspected, so a repository's own Reflection-based
 * plumbing is out of reach by construction.
 *
 * Known limits, both by design:
 *
 * - It catches the Reflection *mechanism* only. A test reaching non-public
 *   state by another backdoor — a `\Closure::bind()` rebind, a subclass that
 *   widens visibility, a debug accessor on the class under test — is invisible
 *   to it.
 * - $reflectionMembers matches on member *name*, not on receiver type, which a
 *   single-file token scan cannot resolve. `$container->invoke($job)` therefore
 *   reports even though no Reflection is involved. Legitimate uses take the
 *   standard per-line suppression (`// phpcs:ignore`), and a ruleset that hits
 *   this often can narrow the property instead.
 *
 * Warnings, not errors, matching CleanCode.Models.DisallowExternalPersistenceCalls
 * and the rest of Testing: Guidelines: the standard is advisory and the
 * name-based half above has known false positives, so a violation must not fail
 * a consumer's build. Detection only — replacing a Reflection call with public
 * API coverage is a redesign of the test, with no mechanical rewrite.
 *
 * Fixtured in tests/fixtures/NoReflectionAccessSniff/ and covered by
 * tests/Standards/NoReflectionAccessTest.php.
 */
class NoReflectionAccessSniff implements Sniff
{
    /**
     * Every token a class name can be made of, in either tokenisation: the
     * pre-8.0 spelling PHP_CodeSniffer backfills to (T_STRING joined by
     * T_NS_SEPARATOR) and PHP 8's single qualified-name tokens, in case a
     * future PHPCS stops backfilling. PHP's floor here is 8.1, so all three
     * T_NAME_* constants are always defined.
     */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    /**
     * Path globs (fnmatch syntax) that mark a file as a test file. The sniff
     * inspects nothing outside them. Configurable from a ruleset via
     * <property name="testFilePatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $testFilePatterns = [
        '*/tests/*',
        '*/Tests/*',
        '*Test.php',
    ];

    /**
     * Reflection classes whose instantiation is itself the violation.
     * Configurable via <property name="reflectionClasses" type="array" .../>.
     *
     * @var array<string>
     */
    public array $reflectionClasses = [
        'ReflectionMethod',
        'ReflectionProperty',
    ];

    /**
     * Reflection member names that reach a named non-public member.
     * Configurable via <property name="reflectionMembers" type="array" .../>.
     *
     * @var array<string>
     */
    public array $reflectionMembers = [
        'getMethod',
        'getProperty',
        'invoke',
        'invokeArgs',
        'setAccessible',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_NEW,
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isTestFile($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_NEW) {
            $this->processInstantiation($phpcsFile, $stackPtr);

            return;
        }

        $this->processMemberAccess($phpcsFile, $stackPtr);
    }

    /**
     * Whether the file's path matches one of the configured test-file globs.
     *
     * Windows separators are normalised to forward slashes first, so one glob
     * spelling matches on either platform. The match itself is case-sensitive:
     * folding case would make `*Test.php` swallow `latest.php` and
     * `greatest.php`, so the two directory spellings that are actually in use
     * are listed out in $testFilePatterns instead.
     */
    private function isTestFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->testFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports `new ReflectionMethod(...)` and friends.
     *
     * The class name is read as a *run* of tokens rather than one token,
     * because PHP_CodeSniffer 3.x backfills PHP 8's single-token qualified
     * names (T_NAME_FULLY_QUALIFIED and friends) into the pre-8.0 spelling —
     * `\ReflectionMethod` arrives as T_NS_SEPARATOR followed by T_STRING.
     * Reading only the first token there yields `\`, which matches nothing.
     * Collecting the run handles both tokenisations, and the loop stops at the
     * first token that cannot be part of a name (whitespace, `(`, a variable
     * for `new $class`, T_CLASS for an anonymous class), so those shapes fall
     * out with an empty name and are never reported.
     *
     * Only the trailing segment is compared: an aliased import cannot be
     * resolved from one file's tokens, so `App\ReflectionMethod` is reported
     * like the global class rather than guessed about.
     */
    private function processInstantiation(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($classPtr === false) {
            return;
        }

        $written = $this->readName($tokens, $classPtr);

        if ($this->matches($this->trailingSegment($written), $this->reflectionClasses) === false) {
            return;
        }

        $this->report($phpcsFile, $classPtr, $written);
    }

    /**
     * The class name written at $startPtr, as source text, gathered from the
     * consecutive name tokens starting there. Empty when $startPtr is not the
     * start of a name at all.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function readName(array $tokens, int $startPtr): string
    {
        $written = '';

        for ($pointer = $startPtr; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return $written;
    }

    /**
     * Reports a configured reflection member reached through `->`, `?->` or
     * `::`. Only a real call is reported: a property read of the same name
     * (`$reflection->invoke`) reaches nothing on its own.
     */
    private function processMemberAccess(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($memberPtr === false || $tokens[$memberPtr]['code'] !== T_STRING) {
            return;
        }

        if ($this->matches($tokens[$memberPtr]['content'], $this->reflectionMembers) === false) {
            return;
        }

        $afterMember = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if ($afterMember === false || $tokens[$afterMember]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $this->report($phpcsFile, $memberPtr, $tokens[$memberPtr]['content']);
    }

    /**
     * The last segment of a possibly-qualified name: `\ReflectionMethod` and
     * `App\ReflectionMethod` both yield `ReflectionMethod`.
     */
    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', $name);

        return (string) end($segments);
    }

    /**
     * Case-insensitive membership, because PHP class and method names are
     * themselves case-insensitive.
     *
     * @param array<string> $candidates
     */
    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }

    /**
     * One warning shape for both detections, so a consumer suppressing
     * `CleanCode.Testing.NoReflectionAccess.Found` silences the rule whole.
     */
    private function report(File $phpcsFile, int $stackPtr, string $name): void
    {
        $phpcsFile->addWarning(
            'Reflection (%s) reaches a non-public member; test through the public API instead'
                . ' (see docs/standards/testing-guidelines.md)',
            $stackPtr,
            'Found',
            [$name]
        );
    }
}

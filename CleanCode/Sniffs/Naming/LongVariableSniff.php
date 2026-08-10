<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's Naming/LongVariable rule.
 *
 * PHPMD flags a field, formal parameter, or local variable whose name is longer
 * than a configured maximum. No PHPCS, Generic, or Slevomat sniff measures a
 * variable name's length, so this custom sniff carries the rule; see
 * docs/phpmd/naming-longvariable.md for the mapping.
 *
 * The detection mirrors PHPMD 2.15.0's own implementation
 * (PHPMD\Rule\Naming\LongVariable plus PHPMD\Utility\Strings), verified by
 * running that version over this sniff's fixtures:
 *
 * - The rule is `ClassAware`, `MethodAware`, `FunctionAware`, and `TraitAware`.
 *   The container tokens are registered here rather than T_VARIABLE itself,
 *   because both the scope a name is de-duplicated within and the decision to
 *   ignore a variable altogether follow from *which* container holds it.
 * - The leading `$` is not counted: PHPMD measures
 *   `ltrim($variableName, '$')`. `$abc` is three characters, not four.
 * - Length is a byte count (PHP's `strlen`), not a character count, because
 *   `Strings::lengthWithoutPrefixesAndSuffixes()` uses `strlen`.
 * - At most one suffix and at most one prefix are subtracted, each being the
 *   *first* entry of its configured list that matches — PHPMD breaks out of
 *   both loops on the first hit, so a longer later entry never wins.
 * - Both subtractions are taken from the length of the *original* name, so a
 *   prefix and a suffix that overlap are each subtracted in full. That is
 *   PHPMD's behaviour and is reproduced rather than corrected.
 * - A violation is raised only when the resulting length is strictly greater
 *   than the maximum: at the threshold exactly, PHPMD stays silent.
 * - A name is reported **once per container**, at its first occurrence, not
 *   once per use — PHPMD keeps a `processedVariables` map and resets it per
 *   node. A class body de-duplicates its fields; a function de-duplicates its
 *   parameters and every variable in its body, closures included, because
 *   PHPMD finds a closure's variables as children of the enclosing function.
 * - A variable that is the object or the member of a member access
 *   (`$object->method()`, `self::$field`, `$object::create()`) is not reported.
 *   PHPMD skips any node under a `MemberPrimaryPrefix`. Array access is not a
 *   member access, so `$someArray['key']` still counts.
 * - That skip happens *after* the name is marked as seen, exactly as PHPMD
 *   orders `addProcessed()` before `checkMaximumLength()`. So a name whose
 *   first occurrence is a member access is silenced for the whole container,
 *   even where a later occurrence is a plain assignment. Reproduced rather
 *   than corrected; `tests/fixtures/LongVariableSniff/ordering.php` pins it.
 * - Code outside any class, trait, interface, enum, or named function is not
 *   examined, including a closure declared at file scope. PHPMD's rule is not
 *   aware of those contexts, so neither is this sniff.
 * - A construct is walked either in its own right or as part of what encloses
 *   it, never both — see isReachableArtifact(). A named class, trait,
 *   interface, enum, or function is an artifact of PDepend's wherever it is
 *   declared — nested in a function body, or in an anonymous class's method —
 *   so it is walked in its own right and stepped over by the enclosing walk. An
 *   anonymous class is not an artifact at all, so it is reached only through
 *   whatever encloses it, and its methods with it — but only its own body is
 *   covered that way, not a named construct declared deeper inside one. At file
 *   scope nothing encloses it, so neither its fields nor the locals of its
 *   methods are reported, in either tool.
 *   `tests/fixtures/LongVariableSniff/nesting.php` pins each shape.
 *
 * Two deliberate divergences, both documented in docs/phpmd/naming-longvariable.md:
 *
 * - A name that only ever appears interpolated into a double-quoted string or
 *   heredoc is not reported. PHPCS tokenises the whole string as one token, so
 *   there is no T_VARIABLE to find. A variable declared or assigned anywhere in
 *   the same container is still reported at that occurrence.
 * - A trait's parameters and locals are reported once. PHPMD reports them
 *   twice, because its `apply()` returns early for a `class` node but not for a
 *   `trait` node, so the trait node walks the whole trait *and* every method
 *   node walks itself. Duplicating a report helps nobody, so the duplicate is
 *   dropped rather than reproduced.
 *
 * A PHP 8.4 property hook is a gap rather than a divergence: PHPMD 2.15.0
 * cannot parse a file containing one, so there is nothing to match. A hooked
 * property is measured like any other field, and the parameters and locals
 * inside its hooks are not — PHPCS opens no scope for a hook body, so they
 * would otherwise arrive at the field walk looking class-scoped.
 * `tests/fixtures/LongVariableSniff/property-hooks.php` pins it.
 *
 * Detection only, matching PHPMD: renaming a variable means rewriting every
 * reference to it, and for a field every reference across the codebase, which a
 * single-file, token-based fixer cannot do safely.
 */
class LongVariableSniff implements Sniff
{
    /**
     * The name-length reporting threshold, measured without the leading `$`. A
     * name longer than this is flagged; a name of exactly this length is not.
     * PHPMD's `maximum` property, same default.
     */
    public int $maximum = 20;

    /**
     * Comma-separated prefixes that do not count towards the name length. Only
     * the first entry that matches is subtracted. PHPMD's `subtract-prefixes`
     * property, same default (none).
     */
    public string $subtractPrefixes = '';

    /**
     * Comma-separated suffixes that do not count towards the name length. Only
     * the first entry that matches is subtracted. PHPMD's `subtract-suffixes`
     * property, same default (none).
     */
    public string $subtractSuffixes = '';

    /**
     * The member-access operators whose neighbour is exempt. A variable on the
     * left of one is the object being accessed; a variable on the right of a
     * `::` is the static field being read.
     *
     * @var array<int, int|string>
     */
    private const MEMBER_ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * The keywords a property declaration opens with, beyond the visibility
     * modifiers. Every property carries at least one of the two sets: since PHP
     * 8.0 a bare `$field;` in a class body is a parse error, and `var` is the
     * pre-5.0 spelling that still parses.
     *
     * @var array<int, int|string>
     */
    private const PROPERTY_MODIFIERS = [
        T_VAR,
        T_STATIC,
        T_READONLY,
        T_FINAL,
    ];

    /**
     * The constructs PDepend models as artifacts of their own, which is exactly
     * the set this sniff registers. A nested one is walked in its own right, so
     * collect() steps over it rather than sweeping its variables into the
     * enclosing scope.
     *
     * T_ANON_CLASS is deliberately absent: PDepend builds no artifact for an
     * anonymous class, so collect() walks into one instead. T_CLOSURE and T_FN
     * are absent for the same reason — PHPMD finds a closure's variables as
     * children of the enclosing function, so they share its scope.
     *
     * @var array<int, int|string>
     */
    private const ARTIFACT_TOKENS = [
        T_FUNCTION,
        T_CLASS,
        T_TRAIT,
        T_INTERFACE,
        T_ENUM,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM, T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isReachableArtifact($phpcsFile, $stackPtr) === false) {
            return;
        }

        $variables = $phpcsFile->getTokens()[$stackPtr]['code'] === T_FUNCTION
            ? $this->functionVariables($phpcsFile, $stackPtr)
            : $this->fieldVariables($phpcsFile, $stackPtr);

        $this->report($phpcsFile, $variables);
    }

    /**
     * Whether PHPMD reaches the construct at $stackPtr as an artifact of its
     * own. This is the single predicate that keeps every variable walked
     * exactly once: process() skips a construct it is false for, and collect()
     * steps over a nested construct it is true for.
     *
     * A named class, trait, interface, enum, or function is an artifact
     * wherever it is declared — PDepend registers one nested inside a function
     * body just as it does one at namespace scope, and a live PHPMD 2.15.0 run
     * confirms it: a nested class's field and a local of the same name in the
     * enclosing function are reported separately, so the two are genuinely
     * different scopes rather than one subtree.
     *
     * An **anonymous class** is the exception, and the only one. PDepend builds
     * no artifact for it, so PHPMD reaches its fields — and its methods, which
     * are its children rather than artifacts of their own — only through
     * whatever encloses it. Inside a method or function that is the enclosing
     * node's own walk; at file scope nothing encloses it, and PHPMD reports
     * neither its fields nor the locals of its methods.
     *
     * That exception reaches exactly one level. Only a construct declared
     * *directly* in an anonymous class body — a method of one — is a non-
     * artifact; a named class or function declared inside that method is an
     * artifact again, because PDepend registers a named construct wherever it
     * is declared. So the test is the innermost enclosing scope, not the whole
     * chain: reading the chain made "inside an anonymous class" contagious, and
     * a named construct nested under one was then walked as part of the nearest
     * real function instead of in its own right — which silently dropped a
     * genuine local of that function to the name-based de-duplication in
     * report(). A live PHPMD 2.15.0 run reports both, separately.
     */
    private function isReachableArtifact(File $phpcsFile, int $stackPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        return end($conditions) !== T_ANON_CLASS;
    }

    /**
     * The fields declared directly in a class-like body. Method bodies and
     * parameter lists are skipped by collect(), and are reached through this
     * sniff's own T_FUNCTION registration instead — which is what gives a field
     * and a same-named local separate de-duplication scopes, as in PHPMD.
     *
     * What collect() leaves behind still has to be filtered down to actual
     * property declarations, because PHP_CodeSniffer opens no scope for a PHP
     * 8.4 property hook: every `$this`, parameter, and local written inside one
     * arrives here looking class-scoped. Counting those would report a hook's
     * locals as fields, and — worse — a hook local sharing a field's name would
     * de-duplicate the field's own report away.
     *
     * @return array<int, int>
     */
    private function fieldVariables(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        // An interface or a forward declaration with no body has no field to
        // measure. `class Foo;` is not valid PHP, but a truncated file is what
        // PHPCS hands a sniff while an editor is mid-keystroke.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return [];
        }

        $candidates = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['scope_opener'] + 1,
            $tokens[$stackPtr]['scope_closer']
        );

        return array_values(array_filter(
            $candidates,
            fn (int $variablePtr): bool => $this->isPropertyDeclaration($phpcsFile, $variablePtr)
        ));
    }

    /**
     * Whether the variable at $variablePtr opens a property declaration — that
     * is, whether the statement it belongs to starts with a visibility or
     * property modifier. A statement inside a property hook's body starts with
     * something else, which is what keeps hook bodies out of the field walk.
     *
     * The statement starts after the nearest preceding `;`, `{`, or `}`, and
     * any attributes between there and the variable are stepped over.
     */
    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            $variablePtr - 1
        );
        $start = $boundary + 1;

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = $tokens[$start]['attribute_closer'] + 1;
        }

        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true)
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true);
    }

    /**
     * A function's declarations followed by its plain variables, in that order.
     * The order is what decides which occurrence of a repeated name is the one
     * reported, and PHPMD walks every `VariableDeclarator` in the subtree
     * before any `Variable`. A declaration here is the formal parameter list
     * first, then anything in the body that opens a declaration: a `static`
     * local, and the fields of a class declared inside the body, both of which
     * PDepend also models as declarators.
     *
     * An abstract or interface method has parameters but no body.
     *
     * @return array<int, int>
     */
    private function functionVariables(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']) === false) {
            return [];
        }

        $parameters = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['parenthesis_opener'] + 1,
            $tokens[$stackPtr]['parenthesis_closer']
        );

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return $parameters;
        }

        $body = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['scope_opener'] + 1,
            $tokens[$stackPtr]['scope_closer']
        );

        $declarations = [];
        $plain = [];

        foreach ($body as $variablePtr) {
            if ($this->isPropertyDeclaration($phpcsFile, $variablePtr)) {
                $declarations[] = $variablePtr;

                continue;
            }

            $plain[] = $variablePtr;
        }

        return array_merge($parameters, $declarations, $plain);
    }

    /**
     * Every variable token between $start and $end, in source order, with any
     * nested artifact — a named function, class, trait, interface, or enum —
     * left out, because process() walks that one in its own right and
     * collecting its names here too would report each of them twice. A nested
     * *class* had to be skipped here as much as a nested function: sweeping a
     * function-nested class's fields into the enclosing function while the
     * class was also walked separately is what reported them twice.
     *
     * The one construct walked into rather than over is an **anonymous class**,
     * for the reason isReachableArtifact() gives: PDepend builds no artifact
     * for it, so this walk is the only thing that reaches its fields — and the
     * only thing that reaches its methods' parameters and locals, which is why
     * a method inside one is walked into as well.
     *
     * Closures and arrow functions are likewise not skipped: PHPMD finds their
     * variables as children of the enclosing function, so they share its
     * de-duplication scope. They carry their own token codes, so they never
     * match the artifact search below in the first place.
     *
     * @return array<int, int>
     */
    private function collect(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $variables = [];
        $current = $start;
        $wanted = array_merge([T_VARIABLE], self::ARTIFACT_TOKENS);

        while ($current < $end) {
            $next = $phpcsFile->findNext($wanted, $current, $end);

            if ($next === false) {
                break;
            }

            if ($tokens[$next]['code'] === T_VARIABLE) {
                $variables[] = $next;
                $current = $next + 1;

                continue;
            }

            if ($this->isReachableArtifact($phpcsFile, $next) === false) {
                $current = $next + 1;

                continue;
            }

            $current = $this->endOfArtifact($tokens, $next) + 1;
        }

        return $variables;
    }

    /**
     * The last token of a nested artifact declaration: its closing brace, or —
     * for an abstract or interface method — its closing parenthesis. Falls back
     * to the keyword itself so a malformed declaration advances the scan by one
     * token rather than looping forever.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function endOfArtifact(array $tokens, int $stackPtr): int
    {
        return $tokens[$stackPtr]['scope_closer']
            ?? $tokens[$stackPtr]['parenthesis_closer']
            ?? $stackPtr;
    }

    /**
     * Reports the first occurrence of each over-long name.
     *
     * A name is marked as seen *before* the member-access exemption is applied,
     * which is what makes that exemption silence the whole container rather
     * than just the one occurrence. PHPMD orders it the same way, calling
     * addProcessed() before checkMaximumLength().
     *
     * @param array<int, int> $variables
     */
    private function report(File $phpcsFile, array $variables): void
    {
        $tokens = $phpcsFile->getTokens();
        $seen = [];

        foreach ($variables as $stackPtr) {
            $name = $tokens[$stackPtr]['content'];

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            if ($this->isMemberAccess($phpcsFile, $stackPtr)) {
                continue;
            }

            $length = $this->lengthWithoutPrefixesAndSuffixes(ltrim($name, '$'));

            if ($length <= $this->maximum) {
                continue;
            }

            $phpcsFile->addError(
                'Name %s is %s characters long; keep it to %s or fewer',
                $stackPtr,
                'TooLong',
                [$name, $length, $this->maximum]
            );
        }
    }

    /**
     * Whether the variable is part of a member access — either the object on
     * the left of `->`, `?->`, or `::`, or the static field on the right of a
     * `::`. PHPMD exempts every node under a `MemberPrimaryPrefix`, so both
     * sides are exempt in both tools.
     *
     * Array access is not a member access in either tool: `$someArray['key']`
     * is followed by a bracket, not by one of these operators, so it is still
     * measured.
     *
     * The neighbour search steps over Tokens::$emptyTokens — whitespace *and*
     * comments — as isPropertyDeclaration() does above. Skipping only
     * whitespace let a comment between the variable and its operator hide the
     * operator, so a variable followed by a block comment and then `->` was
     * measured, while the same line without the comment was exempt. PHPMD
     * parses to an AST, where a comment is trivia that cannot come between the
     * two, so the commented form is exempt there as well.
     */
    private function isMemberAccess(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        foreach ([$before, $after] as $neighbour) {
            if ($neighbour !== false && in_array($tokens[$neighbour]['code'], self::MEMBER_ACCESS_OPERATORS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The byte length of $name less at most one configured prefix and at most
     * one configured suffix, each the first matching entry of its list. Both
     * are subtracted from the original name's length, so an overlapping prefix
     * and suffix are each counted in full — a faithful port of PHPMD's
     * Strings::lengthWithoutPrefixesAndSuffixes().
     *
     * $name arrives with its leading `$` already stripped, because PHPMD
     * measures the name rather than the token.
     */
    private function lengthWithoutPrefixesAndSuffixes(string $name): int
    {
        $length = strlen($name);

        foreach ($this->splitToList($this->subtractSuffixes) as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $length -= strlen($suffix);

                break;
            }
        }

        foreach ($this->splitToList($this->subtractPrefixes) as $prefix) {
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                $length -= strlen($prefix);

                break;
            }
        }

        return $length;
    }

    /**
     * Splits a comma-separated property value into trimmed, non-empty entries,
     * as PHPMD's Strings::splitToList() does. Dropping the empty entries
     * matters on the prefix side: `strncmp($name, '', 0)` is 0 for every name,
     * so an empty prefix would match first, subtract nothing, and — the loop
     * stopping at the first match — strand every real prefix behind it. An
     * empty suffix is inert either way, since `substr($name, -0)` returns the
     * whole name rather than the empty string.
     *
     * @return array<int, string>
     */
    private function splitToList(string $value): array
    {
        return array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $entry): bool => $entry !== ''
        );
    }
}

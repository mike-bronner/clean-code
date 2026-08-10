<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags properties, parameters and local variables whose name is shorter than
 * a configured minimum.
 *
 * Replicates PHPMD's Naming/ShortVariable (#106) —
 * docs/phpmd/naming-shortvariable.md. No PHPCS, Generic, Squiz or Slevomat
 * sniff measures variable-name *length*: Squiz.NamingConventions
 * .ValidVariableName judges casing and underscores,
 * VariableAnalysis.CodeAnalysis.VariableAnalysis judges whether a variable is
 * defined and used, and Slevomat ships no name-length rule at all. A custom
 * sniff is therefore the only option, and this one mirrors PHPMD's own rule
 * class (PHPMD\Rule\Naming\ShortVariable) where PHPMD's behaviour is a rule
 * decision:
 *
 * - The comparison is `strlen($name) >= $threshold` on the name without its
 *   `$` sigil, so a name of exactly `minimum` characters passes and one
 *   character less fails.
 * - Byte length, not character length, because PHPMD calls strlen().
 * - Exceptions are the raw property exploded on commas, with no per-entry
 *   trim and no `$` sigil, again PHPMD's own code. "ab, cd" therefore exempts
 *   `ab` and ` cd`, not `cd`. Trimming here would exempt names PHPMD still
 *   reports, which is the unsafe direction for a ruleset whose purpose is to
 *   make running phpmd unnecessary.
 * - Each distinct name is reported once per scope, at its first occurrence —
 *   PHPMD's `processedVariables` map. A class-like carries one scope for its
 *   property declarations, every named function or method carries one for its
 *   parameters and body, and everything outside both shares the file's scope.
 *   Closures and arrow functions fold into the scope that encloses them,
 *   exactly as pdepend nests them under the method that declares them.
 * - The first occurrence decides for the whole scope: a name whose first
 *   occurrence is exempt is never reported, even where a later occurrence
 *   would be. This is PHPMD marking the name processed before the exemption
 *   is even consulted.
 * - Exempt contexts, from PHPMD's isNameAllowedInContext(): the init section
 *   of a `for` header, the variable a `catch` binds, and the key/value
 *   variables of a `foreach`. A by-reference `foreach` value is *not* exempt
 *   in PHPMD (its ForeachStatement child is the reference expression, whose
 *   image never matches the variable's), and neither is a variable
 *   destructured out of the value, so neither is exempt here.
 * - Variables interpolated into a double-quoted string or a heredoc count as
 *   occurrences, because pdepend parses them into the same variable nodes.
 *   A nowdoc and a single-quoted string interpolate nothing, so neither is
 *   read.
 * - `$this` is never an occurrence, however it is written — bare, as a member
 *   chain's receiver, or interpolated into a string with either syntax. This
 *   is the single place the sniff is quieter than phpmd, because the report
 *   is unactionable: PHP forbids assigning `$this`, so no rename can silence
 *   it. See self::IMPLICIT_RECEIVER for the spellings phpmd does report.
 *
 * Detection only, matching PHPMD: renaming a variable means rewriting every
 * read and write of it, and for a property or parameter every caller too, so
 * there is no safe mechanical rewrite.
 *
 * Four shapes are reported here that phpmd stays silent on, all of them
 * genuine violations of the rule as PHPMD states it. Reporting them is the
 * safe direction — this ruleset replaces phpmd for the rule, so catching more
 * than phpmd never leaves a violation unreported, while catching less would.
 * Each is pinned by tests/fixtures/ShortVariableSniff/divergences.php and
 * documented in docs/phpmd/naming-shortvariable.md:
 *
 * - A name whose first occurrence sits inside a `->` or `::` member-access
 *   chain, including an argument to a method call ($this->run($ab)). PHPMD
 *   exempts the whole MemberPrimaryPrefix subtree.
 * - A name declared inside a `catch` block's body. PHPMD exempts the whole
 *   CatchStatement subtree, not just the variable it binds.
 * - A variable in procedural, file-level code, which pdepend hands no
 *   function, method or class node for.
 * - A property, parameter or local of an anonymous class's methods, which
 *   pdepend builds no nodes for either.
 */
class ShortVariableSniff implements Sniff
{
    /**
     * PHPMD's own default for the `minimum` property, and the value this
     * sniff falls back to when the configured one is unusable.
     */
    public const DEFAULT_MINIMUM = 3;

    /**
     * Shortest acceptable variable name, measured without the `$` sigil.
     * Configurable from a ruleset via <property name="minimum" value="…"/>,
     * matching PHPMD's property name.
     *
     * Left untyped because PHPCS hands ruleset properties over as raw strings
     * and turns an empty value into null; minimum() normalises both.
     *
     * @var int|string|null
     */
    public $minimum = self::DEFAULT_MINIMUM;

    /**
     * Comma-separated variable names, written without the `$` sigil, that are
     * never reported however short. Configurable from a ruleset via
     * <property name="exceptions" value="…"/>, matching PHPMD's property name
     * and its comma-separated format.
     *
     * @var string|null
     */
    public $exceptions = '';

    /**
     * PHP's implicit receiver, written without its `$` sigil.
     *
     * Never an occurrence, however it is written. `this` is four characters,
     * so the exclusion changes nothing at the default minimum of 3 — it is
     * what keeps a ruleset that raises `minimum` to 5 or more from reporting
     * every `$this` in the codebase.
     *
     * This is the one place the sniff is deliberately *quieter* than phpmd,
     * and the reason is that the report is unactionable rather than wrong:
     * PHP forbids assigning `$this`, so the name is never one an author chose
     * and no rename can silence it. A user meeting it could only add `this` to
     * the exceptions list. Every other divergence goes the other way, because
     * reporting more than phpmd cannot hide a real violation — and no real
     * violation hides here either, since there is nothing to fix.
     *
     * Verified against phpmd 2.15, which is not uniform about it. pdepend
     * folds a member chain's receiver into a MemberPrimaryPrefix and builds no
     * variable node for it, so phpmd stays silent on `$this->value` and on the
     * braced interpolation `"{$this->value}"`. Everywhere `$this` stands as an
     * ordinary expression it does get a node, and phpmd reports it at
     * `minimum` 5: `return $this;`, `"{$this}"`, `"$this"`, and — because
     * pdepend's simple-interpolation parser reads the name without building
     * the member access around it — `"$this->value"` and its heredoc spelling.
     * Those five are what this sniff drops and phpmd does not; the matrix is
     * pinned by tests/fixtures/ShortVariableSniff/implicit-receiver.php.
     *
     * Dropped rather than exempted, for the reason isStaticMemberAccess()
     * gives: this is not an occurrence at all. The comparison is
     * case-sensitive because PHP variable names are — `$This` is a different,
     * ordinary variable, and both tools measure it.
     */
    private const IMPLICIT_RECEIVER = 'this';

    /**
     * The class-like tokens that own a property-declaration scope and hide
     * their body from the scope enclosing them.
     *
     * @var array<int, int|string>
     */
    private const CLASS_LIKE_TOKENS = [T_CLASS, T_ANON_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * The tokens that open a nesting a `foreach` header's loop variables are
     * never found inside — an array destructure or a call in the value
     * position.
     *
     * @var array<int, int|string>
     */
    private const NESTING_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_PARENTHESIS];

    /**
     * Their closers.
     *
     * @var array<int, int|string>
     */
    private const NESTING_CLOSERS = [T_CLOSE_SHORT_ARRAY, T_CLOSE_SQUARE_BRACKET, T_CLOSE_PARENTHESIS];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return array_merge([T_OPEN_TAG, T_FUNCTION], self::CLASS_LIKE_TOKENS);
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $occurrences = $this->scopeOccurrences($phpcsFile, $stackPtr);

        $this->reportFirstOccurrences($phpcsFile, $occurrences);
    }

    /**
     * Every variable occurrence belonging to the scope this token owns, in
     * source order.
     *
     * @return array<int, array{pointer: int, name: string}>
     */
    private function scopeOccurrences(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_OPEN_TAG) {
            // Only the first open tag owns the file scope. A file that closes
            // and reopens its PHP block has several, and processing each one
            // would report the same name once per tag.
            return $phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) === false
                ? $this->occurrencesIn($phpcsFile, 0, ($phpcsFile->numTokens - 1))
                : [];
        }

        if ($code === T_FUNCTION) {
            return $this->functionOccurrences($phpcsFile, $stackPtr);
        }

        // A class-like owns only its property declarations: its methods are
        // T_FUNCTION scopes of their own, reached separately.
        $isBounded = isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']);

        return $isBounded === true
            ? $this->occurrencesIn(
                $phpcsFile,
                ($tokens[$stackPtr]['scope_opener'] + 1),
                ($tokens[$stackPtr]['scope_closer'] - 1)
            )
            : [];
    }

    /**
     * A function's own parameters and body.
     *
     * The range starts at the parameter list, so parameters belong to the
     * function that declares them rather than to the scope around it, and
     * ends at the body's closing brace — or at the parameter list's own
     * closing parenthesis for an abstract or interface method, which has no
     * body.
     *
     * A declaration PHPCS could not find a parameter list for is left alone
     * entirely: without that boundary there is nothing proving which tokens
     * belong to this declaration at all, and guessing is the open behaviour.
     *
     * @return array<int, array{pointer: int, name: string}>
     */
    private function functionOccurrences(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        $opener = $tokens[$stackPtr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if ($opener === null || $closer === null) {
            return [];
        }

        return $this->occurrencesIn($phpcsFile, $opener, $tokens[$stackPtr]['scope_closer'] ?? $closer);
    }

    /**
     * Every variable occurrence between two pointers that is not owned by a
     * nested scope, in source order.
     *
     * A nested named function owns its parameters as well as its body, so the
     * skip starts at its keyword. A nested class-like owns only what its
     * braces enclose, so the skip starts at its opening brace — which leaves
     * the arguments of `new class ($ab) { … }` where they belong, in the
     * scope that writes them.
     *
     * @return array<int, array{pointer: int, name: string}>
     */
    private function occurrencesIn(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $occurrences = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_FUNCTION) {
                $pointer = $this->endOfDeclaration($phpcsFile, $pointer);

                continue;
            }

            if ($this->isClassLikeBrace($phpcsFile, $pointer) === true) {
                $pointer = $tokens[$pointer]['scope_closer'];

                continue;
            }

            if ($code === T_VARIABLE) {
                $name = substr($tokens[$pointer]['content'], 1);

                $isOccurrence = $name !== self::IMPLICIT_RECEIVER
                    && $this->isStaticMemberAccess($phpcsFile, $pointer) === false;

                if ($isOccurrence === true) {
                    $occurrences[] = ['pointer' => $pointer, 'name' => $name];
                }

                continue;
            }

            foreach ($this->interpolatedNames($tokens[$pointer]) as $name) {
                // A string spells the receiver too, and it is dropped here for
                // the same reason — see self::IMPLICIT_RECEIVER, which records
                // that phpmd does report several of these spellings.
                if ($name === self::IMPLICIT_RECEIVER) {
                    continue;
                }

                $occurrences[] = ['pointer' => $pointer, 'name' => $name];
            }
        }

        return $occurrences;
    }

    /**
     * Whether the variable is the property half of a static access —
     * `self::$field`, `Other::$field`, `$object::$field`.
     *
     * PHPCS spells that property with a T_VARIABLE token; pdepend builds no
     * variable node for it, exactly as it builds none for the `$this->field`
     * spelling of the same access. It is therefore not an occurrence at all,
     * rather than an occurrence that is exempt: an exempt occurrence would
     * still consume the name's one report for the scope, silencing a genuine
     * violation written later in the same body.
     */
    private function isStaticMemberAccess(File $phpcsFile, int $pointer): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        return $previous !== false
            && $phpcsFile->getTokens()[$previous]['code'] === T_DOUBLE_COLON;
    }

    /**
     * The last token of a nested declaration, so the caller can step past it.
     *
     * A method with a body ends at its closing brace, an abstract or
     * interface method at the semicolon that terminates it. A declaration
     * with neither is malformed, and stepping only past the keyword lets the
     * scan continue rather than swallowing the rest of the file.
     */
    private function endOfDeclaration(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
            return $tokens[$stackPtr]['scope_closer'];
        }

        if (isset($tokens[$stackPtr]['parenthesis_closer']) === false) {
            return $stackPtr;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $tokens[$stackPtr]['parenthesis_closer']);

        return $semicolon === false ? $tokens[$stackPtr]['parenthesis_closer'] : $semicolon;
    }

    /**
     * Whether this token is the opening brace of a class, interface, trait,
     * enum or anonymous class.
     */
    private function isClassLikeBrace(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();

        $isScopeOwner = isset($tokens[$pointer]['scope_condition'], $tokens[$pointer]['scope_closer']);

        if ($isScopeOwner === false) {
            return false;
        }

        if ($tokens[$pointer]['scope_opener'] !== $pointer) {
            return false;
        }

        return in_array(
            $tokens[$tokens[$pointer]['scope_condition']]['code'],
            self::CLASS_LIKE_TOKENS,
            true
        );
    }

    /**
     * The variable names a string token interpolates, in source order.
     *
     * Only a double-quoted string and a heredoc interpolate; PHPCS gives a
     * nowdoc and a single-quoted string their own token types, so neither
     * reaches the match. The pattern covers PHP's simple `$name` and
     * `{$name}` syntaxes and the deprecated `${name}` one, and skips a `$`
     * the string escapes.
     *
     * @param array<string, mixed> $token
     *
     * @return array<int, string>
     */
    private function interpolatedNames(array $token): array
    {
        if (in_array($token['code'], [T_DOUBLE_QUOTED_STRING, T_HEREDOC], true) === false) {
            return [];
        }

        preg_match_all(
            '/(?<!\\\\)\$\{?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/',
            (string) $token['content'],
            $matches
        );

        return $matches[1];
    }

    /**
     * Reports the first occurrence of every name the scope must not carry.
     *
     * The order of the three tests is PHPMD's: length, then context, then the
     * exceptions list. Only the first occurrence of a name is examined at all,
     * so an exempt first occurrence silences the name for the whole scope.
     *
     * @param array<int, array{pointer: int, name: string}> $occurrences
     */
    private function reportFirstOccurrences(File $phpcsFile, array $occurrences): void
    {
        $minimum = $this->minimum();
        $exceptions = $this->exceptions();
        $seen = [];

        foreach ($occurrences as $occurrence) {
            $name = $occurrence['name'];

            if (isset($seen[$name]) === true) {
                continue;
            }

            $seen[$name] = true;

            if (strlen($name) >= $minimum) {
                continue;
            }

            if ($this->isAllowedInContext($phpcsFile, $occurrence['pointer']) === true) {
                continue;
            }

            if (in_array($name, $exceptions, true) === true) {
                continue;
            }

            $phpcsFile->addError(
                'Avoid variables with short names like %s. Configured minimum length is %s.',
                $occurrence['pointer'],
                'TooShort',
                ['$' . $name, $minimum]
            );
        }
    }

    /**
     * Whether a short name is acceptable where it stands, matching the
     * contexts PHPMD's isNameAllowedInContext() allows.
     */
    private function isAllowedInContext(File $phpcsFile, int $pointer): bool
    {
        return $this->isInForInit($phpcsFile, $pointer) === true
            || $this->isOwnedBy($phpcsFile, $pointer, T_CATCH) === true
            || $this->isForeachLoopVariable($phpcsFile, $pointer) === true;
    }

    /**
     * Whether the token sits in the init section of a `for` header — before
     * the header's first semicolon.
     *
     * PHPMD's test is `isChildOf(ForInit)`, which walks every ancestor, so a
     * variable nested inside an expression in the init section counts too;
     * every enclosing parenthesis is therefore examined, not just the
     * innermost.
     */
    private function isInForInit(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$pointer]['nested_parenthesis'] ?? []) as $opener) {
            if ($this->parenthesisOwnerCode($phpcsFile, (int) $opener) !== T_FOR) {
                continue;
            }

            $semicolon = $this->headerSemicolon($phpcsFile, (int) $opener);

            if ($semicolon === null) {
                continue;
            }

            if ($pointer < $semicolon) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first semicolon that separates a `for` header's sections, or null
     * for a header that has none.
     *
     * The semicolon has to sit at the header's own nesting, or a `for` whose
     * init section calls a closure would end its init at the first statement
     * of that closure's body.
     */
    private function headerSemicolon(File $phpcsFile, int $opener): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['parenthesis_closer'];
        $depth = count($tokens[$opener]['nested_parenthesis'] ?? []) + 1;
        $conditions = count($tokens[$opener]['conditions'] ?? []);

        for ($pointer = ($opener + 1); $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] !== T_SEMICOLON) {
                continue;
            }

            if (count($tokens[$pointer]['nested_parenthesis'] ?? []) !== $depth) {
                continue;
            }

            if (count($tokens[$pointer]['conditions'] ?? []) === $conditions) {
                return $pointer;
            }
        }

        return null;
    }

    /**
     * Whether the token sits inside the parentheses of the given control
     * structure — the variable a `catch` binds, for the one caller.
     *
     * PHPMD exempts everything under the CatchStatement node, the block
     * included; this exempts only what the header binds. The difference is a
     * documented divergence, not an oversight: a name declared in a catch
     * block is as short as one declared anywhere else, and falling silent on
     * it would leave a real violation unreported.
     *
     */
    private function isOwnedBy(File $phpcsFile, int $pointer, int|string $ownerCode): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$pointer]['nested_parenthesis'] ?? []) as $opener) {
            if ($this->parenthesisOwnerCode($phpcsFile, (int) $opener) === $ownerCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the token is one of a `foreach` header's key or value
     * variables.
     *
     * PHPMD exempts a variable whose image matches one of the foreach node's
     * own children, which is exactly the key and the value when each is a
     * plain variable. A by-reference value and a variable destructured out of
     * the value are children of something else, so PHPMD reports both — and
     * so does this: the loop variables are collected at the header's own
     * bracket nesting only, and a value preceded by `&` is dropped.
     */
    private function isForeachLoopVariable(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $this->enclosingForeachParenthesis($phpcsFile, $pointer);

        if ($opener === null) {
            return false;
        }

        $closer = $tokens[$opener]['parenthesis_closer'];
        $asPointer = $phpcsFile->findNext(T_AS, ($opener + 1), $closer);

        if ($asPointer === false) {
            return false;
        }

        $depth = 0;

        for ($current = ($asPointer + 1); $current < $closer; $current++) {
            $code = $tokens[$current]['code'];

            if (in_array($code, self::NESTING_OPENERS, true) === true) {
                $depth++;

                continue;
            }

            if (in_array($code, self::NESTING_CLOSERS, true) === true) {
                $depth--;

                continue;
            }

            if ($current !== $pointer) {
                continue;
            }

            return $depth === 0 && $this->isByReference($phpcsFile, $current) === false;
        }

        return false;
    }

    /**
     * The innermost `foreach` header the token sits directly inside, or null
     * when the token is nested in a further parenthesised expression.
     *
     * The innermost enclosing parenthesis has to be the header itself: a
     * variable inside a call in the value position is a child of that call in
     * PHPMD's tree, not of the foreach, and is reported there too.
     */
    private function enclosingForeachParenthesis(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        if ($openers === []) {
            return null;
        }

        $innermost = (int) end($openers);

        $owner = $this->parenthesisOwnerCode($phpcsFile, $innermost);

        return $owner === T_FOREACH ? $innermost : null;
    }

    /**
     * The code of the token an opening parenthesis belongs to, or null when
     * it belongs to none.
     *
     * PHPCS records `parenthesis_owner` for a control structure's
     * parentheses; a call's parentheses have none, which is what keeps a
     * variable passed to a function out of the `for`, `catch` and `foreach`
     * tests above.
     *
     */
    private function parenthesisOwnerCode(File $phpcsFile, int $opener): int|string|null
    {
        $tokens = $phpcsFile->getTokens();

        return isset($tokens[$opener]['parenthesis_owner']) === true
            ? $tokens[$tokens[$opener]['parenthesis_owner']]['code']
            : null;
    }

    /**
     * Whether the variable is written by reference — `&$value`.
     */
    private function isByReference(File $phpcsFile, int $pointer): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        return $previous !== false && $phpcsFile->getTokens()[$previous]['code'] === T_BITWISE_AND;
    }

    /**
     * The configured minimum, normalised to a usable positive integer.
     *
     * A ruleset property arrives as a string, and PHPCS turns an empty one
     * into null. Anything that is not a positive integer falls back to
     * PHPMD's default rather than being cast — (int) null is 0, and a
     * threshold of 0 passes every name, silently disabling the rule instead
     * of reporting the misconfiguration.
     */
    private function minimum(): int
    {
        $configured = filter_var($this->minimum, FILTER_VALIDATE_INT);

        return $configured === false || $configured < 1 ? self::DEFAULT_MINIMUM : $configured;
    }

    /**
     * The configured exceptions, split PHPMD's way.
     *
     * PHPMD explodes the raw property on commas and compares the parts
     * without trimming them; this reproduces that exactly. Its comparison is
     * a loose in_array() and this one is strict, which no input can tell
     * apart: PHP identifiers never spell a number, so no entry and no name
     * can ever be two numeric strings that compare loosely equal while
     * differing as strings. Casting first keeps explode() off a null
     * property, which PHP 8.1 deprecates.
     *
     * @return array<int, string>
     */
    private function exceptions(): array
    {
        return explode(',', (string) $this->exceptions);
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a method, function, closure, or arrow function that takes a boolean
 * flag argument.
 *
 * Replicates PHPMD's CleanCode BooleanArgumentFlag rule
 * (docs/phpmd/cleancode-booleanargumentflag.md, #76). A boolean flag argument
 * means the callee carries two behaviours and the caller picks one, which is a
 * Single Responsibility Principle violation; the fix is to extract each branch
 * into its own method.
 *
 * A parameter is a boolean flag when either half holds:
 *
 * - its native type declaration resolves to `bool` — `bool`, `?bool`,
 *   `bool|null`, `null|bool`;
 * - its default value is the literal `true` or `false`, in any casing.
 *
 * PHPMD only ever tests the *default value*: it reads PDepend's resolved value
 * for the parameter and reports when that value is exactly `true` or `false`.
 * The type-declaration half is this ruleset's own addition, mandated by #76's
 * acceptance criteria — a `bool` parameter with no default is the same defect
 * with the flag made explicit, and this package requires a native type hint on
 * every parameter (SlevomatCodingStandard.TypeHints.ParameterTypeHint in
 * rules.xml), so the untyped shape PHPMD keys on barely occurs here. The extra
 * reports are a strict superset of PHPMD's: everything PHPMD flags is flagged
 * here too, so `phpmd` does not have to run separately for this rule. Every
 * divergence is set out in the doc and pinned by
 * tests/fixtures/DisallowBooleanArgumentFlagSniff/divergences.php.
 *
 * Scope decisions:
 *
 * - Closures and arrow functions are registered alongside named declarations.
 *   PHPMD reaches a closure only when it is nested inside a method or function
 *   (its rule walks that node's whole subtree), so a closure at file scope is a
 *   PHPMD blind spot this sniff does not reproduce.
 * - A variadic parameter is never a flag. `bool ...$flags` is a list of
 *   booleans, not a branch selector, and PHP forbids a default value on a
 *   variadic, so PHPMD cannot report one either.
 * - Detection only. Removing a flag argument means splitting the callee in two
 *   and rewriting every call site, which is a design change and has no
 *   mechanical rewrite. PHPMD offers no fix either.
 *
 * Both configurable properties are spelled exactly as PHPMD spells them, and
 * both match the way PHPMD matches, so a project's existing PHPMD
 * configuration for this rule transfers verbatim. Matching more loosely than
 * PHPMD would exempt code PHPMD still reports, which is the one direction that
 * would put `phpmd` back in the pipeline.
 */
class DisallowBooleanArgumentFlagSniff implements Sniff
{
    /**
     * Comma-separated class names whose declarations are exempt, matched
     * against the *unqualified* name of the enclosing class, interface, trait,
     * or enum — PDepend's `getName()`, which PHPMD compares against, drops the
     * namespace. Matching is case-sensitive, as PHPMD's own `array_flip()`
     * lookup is. The sibling CleanCode.Models.RequireLazyLoadingPrevention
     * sniff deliberately matches its class list case-insensitively; that list
     * answers to no external tool, this one does.
     */
    public string $exceptions = '';

    /**
     * A PCRE pattern; a method or function whose name matches it is exempt.
     * PHPMD's own example is `/^(__construct|get.*Filtered)$/`. Empty by
     * default, exactly as PHPMD's cleancode.xml ships it — there is no default
     * exemption for constructors or for anything else.
     */
    public string $ignorepattern = '';

    /**
     * Tokens that can own a declaration and carry a name for `exceptions` to
     * match. T_ANON_CLASS is included so that an anonymous class terminates the
     * search at itself rather than letting a method of it inherit the exemption
     * of an outer named class; `getDeclarationName()` returns null for it.
     */
    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLOSURE, T_FN, T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        // getDeclarationName() throws on T_FN and answers null for T_CLOSURE,
        // so only a named declaration is ever asked for its name.
        $name = $code === T_FUNCTION ? $phpcsFile->getDeclarationName($stackPtr) : null;

        if ($this->isIgnoredName($name) === true) {
            return;
        }

        $className = $this->enclosingClassName($phpcsFile, $stackPtr);

        if ($className !== null && $this->isExceptedClass($className) === true) {
            return;
        }

        $subject = $this->describe($code, $name, $className);

        // A declaration cut short mid-edit has no parenthesis pair, and
        // getMethodParameters() answers with an empty list rather than raising.
        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if ($this->isBooleanFlag($parameter) === false) {
                continue;
            }

            $phpcsFile->addError(
                'The %s has a boolean flag argument %s, which is a certain sign of a '
                    . 'Single Responsibility Principle violation; extract each branch the '
                    . 'flag selects into its own method '
                    . '(see docs/phpmd/cleancode-booleanargumentflag.md)',
                $parameter['token'],
                'Found',
                [$subject, $parameter['name']]
            );
        }
    }

    /**
     * Whether the configured ignore pattern exempts this declaration.
     *
     * An unnamed declaration — a closure or an arrow function — has no name to
     * match, so the pattern never exempts one. PHPMD tests the pattern against
     * the *enclosing* method's name in that case, which is why a closure inside
     * an exempted method is a documented divergence.
     *
     * preg_match() answers false rather than 0 on a malformed pattern, and the
     * strict comparison below turns that into "not exempt": a configuration
     * mistake reports too much, never too little.
     */
    private function isIgnoredName(?string $name): bool
    {
        $pattern = trim($this->ignorepattern);

        return $name !== null
            && $pattern !== ''
            && preg_match($pattern, $name) === 1;
    }

    /**
     * Whether the configured exception list names this class.
     */
    private function isExceptedClass(string $className): bool
    {
        $names = array_map('trim', explode(',', $this->exceptions));

        return in_array($className, $names, true);
    }

    /**
     * The unqualified name of the innermost class-like scope the declaration
     * sits in, or null when it sits at file scope or inside an anonymous class.
     */
    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $ptr => $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return $phpcsFile->getDeclarationName($ptr);
            }
        }

        return null;
    }

    /**
     * Names the declaration for the diagnostic: "method render()",
     * "function render()", "closure", or "arrow function".
     */
    private function describe(int|string $code, ?string $name, ?string $className): string
    {
        return match (true) {
            $code === T_CLOSURE => 'closure',
            $code === T_FN => 'arrow function',
            $name === null => 'function',
            $className !== null => "method {$name}()",
            default => "function {$name}()",
        };
    }

    /**
     * @param array<string, mixed> $parameter One entry of getMethodParameters().
     */
    private function isBooleanFlag(array $parameter): bool
    {
        if ($parameter['variable_length'] === true) {
            return false;
        }

        return $this->isBooleanType((string) $parameter['type_hint']) === true
            || $this->isBooleanDefault($parameter['default'] ?? null) === true;
    }

    /**
     * Whether a native type declaration resolves to plain `bool`. The leading
     * `?` and a `null` union member are stripped first, so `bool`, `?bool`,
     * `bool|null`, and `null|bool` all qualify — they differ only in whether
     * the flag has a third state. A wider union such as `bool|string` does not:
     * there the parameter carries a value, not a branch selector.
     */
    private function isBooleanType(string $typeHint): bool
    {
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $typeHint) ?? ''), '?');
        $types = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return $types === ['bool'];
    }

    /**
     * Whether a default value is a boolean literal, in any casing — the half of
     * the detection PHPMD implements. PHPCS hands over the default expression
     * verbatim, so anything that merely evaluates to a boolean (`self::ENABLED`,
     * `!true`) is not one; PDepend cannot resolve those either, so PHPMD is
     * silent on them too.
     */
    private function isBooleanDefault(?string $default): bool
    {
        return in_array(strtolower(trim((string) $default)), ['true', 'false'], true);
    }
}

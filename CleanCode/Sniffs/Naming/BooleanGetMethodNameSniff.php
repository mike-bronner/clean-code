<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags a method named `getX()` that is declared to return a boolean. The
 * convention is `isX()` or `hasX()`: a getter promises to hand back a value,
 * while a yes/no answer reads as a question at every call site.
 *
 * Replicates PHPMD's Naming BooleanGetMethodName rule
 * (docs/phpmd/naming-booleangetmethodname.md, #116). PHPMD's own three
 * conditions are reproduced exactly, and every one of them was read from
 * PHPMD 2.15.0's source rather than from its documentation page, which
 * paraphrases the name pattern as `^get[A-Z_]`:
 *
 * - the name matches `(^_?get)i` — case-insensitive, optional leading
 *   underscore, and *no* requirement that a capital follow, so `getterCached()`
 *   and `GetDraft()` are both getters to PHPMD;
 * - the declaration says it returns a boolean;
 * - the `checkParameterizedMethods` property allows it (see below).
 *
 * Methods only, as PHPMD's rule is MethodAware: a plain function, a closure,
 * an arrow function, and a named function nested inside a method are all left
 * alone. Only the *innermost* enclosing scope decides, since a function
 * declared in a method still lists that method's class among its conditions.
 *
 * Two halves answer "is it declared boolean", where PHPMD has one:
 *
 * - the `@return` tag of the doc comment — PHPMD's only source;
 * - the native return type — this ruleset's addition, mandated by #116's
 *   acceptance criteria. rules.xml requires a native return type on every
 *   method (SlevomatCodingStandard.TypeHints.ReturnTypeHint), so in code this
 *   ruleset governs the native declaration is the one that is always present
 *   and the doc comment is the optional extra.
 *
 * Both halves are read through the same normalisation, so `bool`, `boolean`,
 * `?bool`, and `bool|null` all count and a wider union such as `bool|string`
 * does not — that parameter carries a value, not a yes/no answer. PHPMD's own
 * pattern is narrower on the doc-comment half (it wants `bool` or `boolean`
 * directly after `@return`, so `?bool` and `bool|null` slip past it), which
 * makes this sniff a strict superset: everything PHPMD reports is reported
 * here too, so `phpmd` does not have to run separately for this rule. Every
 * divergence is set out in the doc and pinned by
 * tests/fixtures/BooleanGetMethodNameSniff/divergences.php.
 *
 * Detection only. Renaming `getX()` to `isX()` rewrites every call site, and
 * `is` versus `has` is a judgement about what the method asks — neither is a
 * mechanical rewrite. PHPMD offers no fix either.
 */
class BooleanGetMethodNameSniff implements Sniff
{
    /**
     * Spelled exactly as PHPMD spells it, and matched the way PHPMD matches:
     * left `false` (PHPMD's own default, from naming.xml) every `get*` boolean
     * method is reported; set to `true` only the *parameterless* ones are,
     * because a method that takes arguments computes an answer rather than
     * exposing a stored one.
     *
     * PHPMD's property description reads "Applies only to methods without
     * parameter when set to true", which is easy to read backwards. Its
     * implementation is unambiguous — `isParameterizedOrIgnored()` returns
     * `$node->getParameterCount() === 0` when the property is on and `true`
     * when it is off — and a live PHPMD 2.15.0 run over
     * tests/fixtures/BooleanGetMethodNameSniff/configured.php confirms it:
     * four reports off, one on.
     */
    public bool $checkParameterizedMethods = false;

    /**
     * Scopes whose methods this rule speaks about. T_ANON_CLASS is included:
     * a method of an anonymous class is a method, and PDepend's blind spot
     * there is a documented divergence, not a rule.
     */
    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    /**
     * Scopes that make an enclosed declaration a function rather than a method.
     * Reaching one of these before a class-like token ends the search.
     */
    private const CALLABLE_TOKENS = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isMethod($phpcsFile, $stackPtr) === false) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || $this->isGetterName($name) === false) {
            return;
        }

        if ($this->isParameterCountAllowed($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->declaresBooleanReturn($phpcsFile, $stackPtr) === false) {
            return;
        }

        // Reported at the name, since the name is what the rule asks to be
        // changed. getDeclarationName() resolves the name by scanning forward
        // for the first T_STRING, so the two always agree on the same token
        // and the search above cannot answer with a name this one misses.
        $phpcsFile->addError(
            'The %s() method returns a boolean, so it should be named "is...()" or '
                . '"has...()" — a getter hands back a value, a question answers yes or no '
                . '(see docs/phpmd/naming-booleangetmethodname.md)',
            $phpcsFile->findNext(T_STRING, $stackPtr),
            'Found',
            [$name]
        );
    }

    /**
     * Whether the declaration is a method — that is, whether the innermost
     * scope holding it is class-like.
     *
     * Reading only the innermost scope is what tells a method apart from a
     * named function declared inside one: the nested function's conditions
     * list still holds the enclosing class, so a search for "any class-like
     * condition" would call it a method. PHPMD sees neither, because PDepend
     * never surfaces a nested function to a MethodAware rule.
     */
    private function isMethod(File $phpcsFile, int $stackPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions) as $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return true;
            }

            if (in_array($code, self::CALLABLE_TOKENS, true) === true) {
                return false;
            }
        }

        return false;
    }

    /**
     * PHPMD's `(^_?get)i`, verbatim. Deliberately not the `^get[A-Z_]` the
     * phpmd.org rule page paraphrases it as: that would drop `getterCached()`
     * and `GetDraft()`, both of which PHPMD 2.15.0 reports, and losing a PHPMD
     * finding is the one direction that puts `phpmd` back in the pipeline.
     */
    private function isGetterName(string $name): bool
    {
        return preg_match('/^_?get/i', $name) === 1;
    }

    /**
     * Whether `checkParameterizedMethods` lets this declaration be reported.
     *
     * Off (the default), every method qualifies. On, only a parameterless one
     * does — every parameter counts, optional and variadic alike, because
     * PHPMD asks PDepend for the parameter count rather than for the required
     * ones.
     */
    private function isParameterCountAllowed(File $phpcsFile, int $stackPtr): bool
    {
        return $this->checkParameterizedMethods === false
            || $phpcsFile->getMethodParameters($stackPtr) === [];
    }

    /**
     * Whether either declaration of the return type resolves to a boolean.
     */
    private function declaresBooleanReturn(File $phpcsFile, int $stackPtr): bool
    {
        $properties = $phpcsFile->getMethodProperties($stackPtr);

        if ($this->isBooleanType((string) $properties['return_type']) === true) {
            return true;
        }

        foreach ($this->docCommentReturnTypes($phpcsFile, $stackPtr) as $type) {
            if ($this->isBooleanType($type) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The type of every `@return` tag on the declaration's doc comment.
     *
     * A tag with no type at all contributes nothing rather than borrowing the
     * next tag's text: the search stops at whichever comes first, a string or
     * another tag.
     *
     * @return array<int, string>
     */
    private function docCommentReturnTypes(File $phpcsFile, int $stackPtr): array
    {
        $commentEnd = $this->docCommentEnd($phpcsFile, $stackPtr);

        if ($commentEnd === null) {
            return [];
        }

        $tokens = $phpcsFile->getTokens();
        $types = [];

        foreach ($tokens[$tokens[$commentEnd]['comment_opener']]['comment_tags'] as $tag) {
            if (strtolower($tokens[$tag]['content']) !== '@return') {
                continue;
            }

            $next = $phpcsFile->findNext(
                [T_DOC_COMMENT_STRING, T_DOC_COMMENT_TAG],
                $tag + 1,
                $commentEnd
            );

            if ($next === false || $tokens[$next]['code'] !== T_DOC_COMMENT_STRING) {
                continue;
            }

            $types[] = preg_split('/\s+/', trim($tokens[$next]['content']))[0];
        }

        return $types;
    }

    /**
     * The closing tag of the doc comment attached to the declaration, or null
     * when it carries none.
     *
     * Attributes are stepped over rather than treated as the end of the
     * search, so a doc comment keeps its meaning wherever the attributes sit
     * relative to it. Anything else between the comment and the declaration
     * means the comment belongs to something further up the file.
     */
    private function docCommentEnd(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ignore = Tokens::$methodPrefixes;
        $ignore[T_WHITESPACE] = T_WHITESPACE;
        $search = $stackPtr;

        while (true) {
            $previous = $phpcsFile->findPrevious($ignore, $search - 1, null, true);

            if ($previous === false) {
                return null;
            }

            if ($tokens[$previous]['code'] === T_ATTRIBUTE_END) {
                $search = $tokens[$previous]['attribute_opener'];

                continue;
            }

            return $tokens[$previous]['code'] === T_DOC_COMMENT_CLOSE_TAG ? $previous : null;
        }
    }

    /**
     * Whether a written type resolves to a plain boolean. `boolean` is folded
     * into `bool` because PHPMD accepts both spellings, and the leading `?`
     * and a `null` union member are stripped because they only add a third
     * state to the same yes/no answer. A wider union such as `bool|string`
     * does not qualify: that method returns a value, not an answer.
     */
    private function isBooleanType(string $type): bool
    {
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $type) ?? ''), '?');
        $members = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return array_map(
            static fn (string $member): string => $member === 'boolean' ? 'bool' : $member,
            $members
        ) === ['bool'];
    }
}

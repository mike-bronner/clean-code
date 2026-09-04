<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class BooleanGetMethodNameSniff implements Sniff
{
    public bool $checkParameterizedMethods = false;

    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    private const CALLABLE_TOKENS = [
        T_CLOSURE,
        T_FN,
        T_FUNCTION,
    ];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isMethod($phpcsFile, $stackPtr) === false) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || $this->isGetterName($name) === false
        ) {
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
            "The %s() method returns a boolean, so it should be named \"is...()\" or "
                . "\"has...()\" — a getter hands back a value, a question answers yes or no "
                . '(see docs/phpmd/naming-booleangetmethodname.md)',
            $phpcsFile->findNext(T_STRING, $stackPtr),
            'Found',
            [$name]
        );
    }

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

    private function isGetterName(string $name): bool
    {
        return preg_match('/^_?get/i', $name) === 1;
    }

    private function isParameterCountAllowed(File $phpcsFile, int $stackPtr): bool
    {
        return $this->checkParameterizedMethods === false
            || $phpcsFile->getMethodParameters($stackPtr) === [];
    }

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

            if (
                $next === false
                || $tokens[$next]['code'] !== T_DOC_COMMENT_STRING
            ) {
                continue;
            }

            $annotation = trim($tokens[$next]['content']);

            // A failed split is false, and `false[0]` reads an offset off a
            // boolean: null, with a warning, rather than a type. The whole
            // annotation is the honest fallback — it still starts with the
            // written type, so `bool` is read as `bool` and only a trailing
            // description rides along. `/\s+/` is one auto-possessified
            // quantifier with no `/u` modifier, so preg_split() cannot fail.
            $types[] = (preg_split('/\s+/', $annotation) ?: [$annotation])[0];
        }

        return $types;
    }

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

    private function isBooleanType(string $type): bool
    {
        // The written type rather than '' on a failed read: '' resolves to no
        // members at all, which reads exactly like a type that is not boolean,
        // so the failure would silently exempt the method from the check. The
        // written type still resolves correctly whenever it carries no internal
        // whitespace, which is every type PHPCS hands over from a native
        // declaration. `/\s+/` is one auto-possessified quantifier, no `/u`.
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $type) ?? $type), '?');
        $members = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return array_map(
            static fn (string $member): string => $member === 'boolean' ? 'bool' : $member,
            $members
        ) === ['bool'];
    }
}

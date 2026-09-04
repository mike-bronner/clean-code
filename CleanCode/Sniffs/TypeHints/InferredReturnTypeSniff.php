<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\ReturnTypeInference;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\FunctionHelper;

// Writes the return type SlevomatCodingStandard.TypeHints.ReturnTypeHint can
// only ask for.
//
// That sniff writes a native hint solely where a @return annotation already
// states one, so a codebase with no docblocks gets a report and no fix. This
// one carries the fixer for declarations whose type is *provable* from the
// source, and stays silent everywhere else.
//
// It runs alongside the Slevomat sniff rather than extending it. Subclassing
// looked cleaner and is not viable: that sniff builds every message code from a
// `private const NAME` through a `private` method using `self::`, so a subclass
// cannot override either, and the branches that resolve severity through the
// hardcoded name are dropped once the parent is unregistered. Two reports on
// one line until `phpcbf` runs is the honest price; the fix clears both.
class InferredReturnTypeSniff implements Sniff
{
    public function register(): array
    {
        return [
            T_FUNCTION,
            T_CLOSURE,
        ];
    }

    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint -- interface-mandated, see CONTRIBUTING.md
    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isSkippable($phpcsFile, $stackPtr) === true) {
            return;
        }

        $type = (new ReturnTypeInference())->infer($phpcsFile, $stackPtr);

        if ($type === null) {
            return;
        }

        $this->report($phpcsFile, $stackPtr, $type);
    }

    private function report(File $phpcsFile, int $stackPtr, string $type): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr) ?? 'Closure';
        $fix = $phpcsFile->addFixableError(
            "%s has no return type hint; \"%s\" follows from its own declaration",
            $stackPtr,
            'Inferable',
            [$name, $type]
        );

        if ($fix === false) {
            return;
        }

        $closer = $this->parameterListCloser($phpcsFile, $stackPtr);

        if ($closer === null) {
            return;
        }

        $phpcsFile->fixer
            ->addContent($closer, ": {$type}");
    }

    private function isSkippable(File $phpcsFile, int $stackPtr): bool
    {
        if ($this->isMagicMethod($phpcsFile, $stackPtr) === true) {
            return true;
        }

        if ($this->hasReturnType($phpcsFile, $stackPtr) === true) {
            return true;
        }

        // A @return annotation is the Slevomat sniff's own case: it writes the
        // native hint from the annotation and reports the more specific
        // MissingNativeTypeHint. Reading the body instead would answer a
        // narrower type than the author documented.
        return FunctionHelper::findReturnAnnotation($phpcsFile, $stackPtr) !== null;
    }

    // Every magic method is left alone. PHP refuses to load a class whose
    // __construct() or __destruct() declares a return type at all, so writing
    // the `void` their bodies imply would be asking for a fatal; __clone() and
    // __toString() accept one but carry their own rules, which the Slevomat
    // sniff already applies.
    private function isMagicMethod(File $phpcsFile, int $stackPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        return $name !== null
            && str_starts_with(strtolower($name), '__');
    }

    private function hasReturnType(File $phpcsFile, int $stackPtr): bool
    {
        $closer = $this->parameterListCloser($phpcsFile, $stackPtr);

        if ($closer === null) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        return $next !== false
            && $phpcsFile->getTokens()[$next]['code'] === T_COLON;
    }

    private function parameterListCloser(File $phpcsFile, int $stackPtr): ?int
    {
        return $phpcsFile->getTokens()[$stackPtr]['parenthesis_closer'] ?? null;
    }
}

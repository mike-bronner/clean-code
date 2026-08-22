<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags an interface that declares more method signatures than the configured
 * maximum.
 *
 * Partial enforcement of Pattern: SOLID — Interface Segregation
 * (docs/standards/pattern-solid.md, #132, spun out of #5). Whether *clients*
 * are forced to depend on methods they do not use is a cross-codebase
 * judgement and stays with code review, but the classic symptom is countable
 * in a single file: the wider an interface's surface, the likelier some
 * signature does not apply to every implementer, which is precisely what the
 * standard says should trigger a split.
 *
 * Interfaces only. This is not a restatement of the class-size metrics the
 * same standard's Single Responsibility half already ships:
 * CleanCode.CodeSize.TooManyMethods (#80) registers on T_CLASS and
 * CleanCode.Classes.TooManyPublicMethods (#83) and
 * CleanCode.Metrics.ExcessivePublicCount (#96) count public members of a
 * class, so no shipped sniff ever sees an interface — PHPCS gives interfaces,
 * traits, enums and anonymous classes their own tokens (T_INTERFACE, T_TRAIT,
 * T_ENUM, T_ANON_CLASS), and registering T_INTERFACE alone is what confines
 * this count to the one construct the ISP slice is about.
 *
 * The threshold is deliberately far below those class-oriented caps. An
 * interface is a contract every implementer has to honour whole, so the count
 * at which a wide surface becomes a segregation question is much lower than
 * the count at which a class becomes a size question.
 *
 * What the count reads, and why each part is decidable from one file:
 *
 * - **Signatures declared directly in the body.** The count runs between the
 *   interface's scope_opener and scope_closer, so an `extends` list — which
 *   sits ahead of the opener — contributes nothing, and neither do the
 *   signatures those parent interfaces declare in their own files. The number
 *   reported is what this file states, which is the only number a single-file
 *   sniff can stand behind.
 * - **Methods only.** Constants are T_CONST and PHP 8.4 property hooks
 *   (`public string $name { get; set; }`) declare no T_FUNCTION at all, so
 *   neither reaches the count. Closures and arrow functions carry their own
 *   tokens (T_CLOSURE, T_FN) and are never found by this search — and an
 *   interface method is bodyless, so there is no body for one to sit in.
 * - **The threshold is exclusive.** An interface holding exactly maxMethods
 *   signatures is compliant; maxMethods + 1 is reported. The property is a
 *   ceiling, not a target.
 * - **Reported on the interface declaration.** The defect is the width of the
 *   whole contract, so it has no statement line of its own.
 *
 * Warning-level and detection-only, matching every other rule this standard
 * carries: a wide interface every implementer fully honours is not an ISP
 * violation, so the report is a prompt for the review conversation rather than
 * a verdict — and splitting a contract means deciding which client needs which
 * signature, which no mechanical rewrite can do.
 */
class TooManyInterfaceMethodsSniff implements Sniff
{
    /**
     * The largest number of method signatures an interface may declare. An
     * interface with exactly this many is compliant.
     */
    public int $maxMethods = 5;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_INTERFACE];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'] ?? null;
        $closer = $tokens[$stackPtr]['scope_closer'] ?? null;
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // An interface PHPCS tokenised mid-edit carries no body to count, and
        // one whose name has not been typed yet carries nothing to report it
        // under, so there is nothing to say about either yet.
        if ($opener === null || $closer === null || $name === null) {
            return;
        }

        $declared = $this->countMethods($phpcsFile, $opener, $closer);

        if ($declared <= $this->maxMethods) {
            return;
        }

        $phpcsFile->addWarning(
            'Interface %s declares %s method signatures, more than the maximum of %s. A '
                . 'wide interface forces implementers to depend on signatures they do not '
                . 'use, so split it into narrower interfaces along the lines its clients '
                . 'actually use (Interface Segregation, see docs/standards/pattern-solid.md)',
            $stackPtr,
            'MaxExceeded',
            [$name, $declared, $this->maxMethods]
        );
    }

    /**
     * Counts the method signatures declared in the interface body.
     *
     * Every T_FUNCTION between the braces belongs to the interface: an
     * interface method is bodyless, so there is no nested scope for a further
     * declaration to hide in, and the one construct in an interface body that
     * does carry braces — a PHP 8.4 property hook — declares no T_FUNCTION.
     */
    private function countMethods(File $phpcsFile, int $opener, int $closer): int
    {
        $count = 0;
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $closer)) !== false) {
            ++$count;
        }

        return $count;
    }
}

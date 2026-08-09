<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags two or more function or method bodies in one file whose normalized
 * token streams are identical.
 *
 * Partial enforcement of the "Pattern: Don't Repeat Yourself (DRY)" standard
 * (docs/standards/pattern-dont-repeat-yourself-dry.md, #4), spun out as #134.
 * DRY is ultimately about duplicated *knowledge*, which no token walk can see —
 * but exact textual duplication inside one file is token-visible, and that
 * narrow slice is what this sniff reports.
 *
 * Reports a **warning**, never an error. The standard explicitly tolerates
 * duplication until an abstraction is warranted ("don't abstract prematurely"),
 * so the sniff points at abstraction candidates rather than mandating a fix.
 *
 * Detection only. The remedy — extract the shared logic and rewrite both call
 * sites — is a design change with no mechanical rewrite.
 *
 * How two bodies are compared:
 *
 * - Only the tokens *between* a declaration's braces are read, so the signature
 *   plays no part: two bodies match even when their names, parameter names,
 *   visibility, and return types all differ.
 * - Comments and whitespace are stripped (`Tokens::$emptyTokens`), so
 *   reformatting or re-commenting a copy-paste does not hide it.
 * - Every surviving token contributes its **type and its content**, so the match
 *   is exact after normalization. A renamed variable, a changed literal, or a
 *   swapped operator makes two bodies different — near-miss clone detection is
 *   out of scope, being noise-prone at the token level (#134's boundaries).
 * - Each token is encoded length-prefixed (`code:length:content`) and the
 *   encodings are concatenated, which makes the stream self-delimiting: no
 *   separator byte can occur inside a token's content and merge two tokens into
 *   one. The resulting string is compared directly rather than digested, so —
 *   unlike a hash — a collision cannot manufacture a false positive.
 *
 * Scope decisions:
 *
 * - **Same file only.** A PHPCS sniff sees one file's tokens at a time.
 *   Project-wide copy/paste detection is the domain of a dedicated detector
 *   such as `phpcpd`.
 * - **Named declarations only** (`T_FUNCTION`): methods and plain functions.
 *   Closures and arrow functions are anonymous callbacks, usually a handful of
 *   tokens passed inline, and reporting them would mostly restate the
 *   duplication of the declaration that holds them.
 * - **Outermost declarations only.** A declaration nested inside another
 *   declaration's body — a method of an anonymous class returned from a method,
 *   say — is skipped, because its tokens already form part of the enclosing
 *   body's stream: comparing both would report one duplication twice, and the
 *   enclosing report is the actionable one. It also keeps the whole file linear,
 *   since every token then belongs to at most one compared body. The cost is a
 *   deliberate blind spot — identical inner declarations inside two *differing*
 *   outer bodies go unreported — pinned by
 *   tests/fixtures/AvoidDuplicateFunctionBodiesSniff/nested-declarations.php.
 * - **Bodies below $minimumStatements are skipped**, so boilerplate accessors,
 *   empty stubs, and one-line delegations do not fire it.
 *
 * Abstract and interface methods carry no body at all, so they never
 * participate.
 */
class AvoidDuplicateFunctionBodiesSniff implements Sniff
{
    /**
     * How many statements a body must contain before it is compared at all.
     *
     * A statement is counted as a `;` terminator anywhere in the body, at any
     * nesting depth, except the two separators inside a `for (…;…;…)` header —
     * those punctuate one loop, and counting them would let an empty `for` body
     * clear a threshold meant to exclude exactly that.
     *
     * Deliberately untyped. PHP_CodeSniffer assigns a `<property>` value from a
     * ruleset verbatim, as the string it read from the XML, so a native `int`
     * declaration would turn a mistyped threshold into an uncatchable TypeError
     * rather than a lint run. The value is cast where it is read, and floored at
     * 1: a body with no statements is never a duplication candidate, whatever a
     * consumer configures.
     *
     * @var int|string
     */
    public $minimumStatements = 3;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_OPEN_TAG];
    }

    /**
     * Runs once per file, at its first PHP open tag, because duplication is a
     * property of the file rather than of any one token. Driving the whole scan
     * from a single dispatch keeps the sniff stateless: a per-file map built
     * across calls would have to be invalidated by hand, and would carry
     * entries from phpcbf's first pass into its second, where the *original*
     * declaration would then look like a duplicate of itself.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $minimumStatements = max(1, (int) $this->minimumStatements);
        $firstSeenAt = [];

        foreach ($tokens as $functionPtr => $token) {
            if (
                $token['code'] !== T_FUNCTION
                || $this->isComparableDeclaration($tokens, $functionPtr) === false
            ) {
                continue;
            }

            [$body, $statements] = $this->summarizeBody($tokens, $functionPtr);

            if ($statements < $minimumStatements) {
                continue;
            }

            if (isset($firstSeenAt[$body]) === false) {
                $firstSeenAt[$body] = $functionPtr;

                continue;
            }

            $originalPtr = $firstSeenAt[$body];

            $phpcsFile->addWarning(
                'The body of %s is identical to %s on line %d once comments and whitespace are'
                    . ' stripped. Extract the shared logic once the duplication has earned an'
                    . ' abstraction (see docs/standards/pattern-dont-repeat-yourself-dry.md).',
                $functionPtr,
                'Found',
                [
                    $this->describe($phpcsFile, $functionPtr),
                    $this->describe($phpcsFile, $originalPtr),
                    $tokens[$originalPtr]['line'],
                ]
            );
        }
    }

    /**
     * Whether the T_FUNCTION at $functionPtr is a declaration this sniff
     * compares: one carrying a body, and sitting outside every other
     * declaration's body.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isComparableDeclaration(array $tokens, int $functionPtr): bool
    {
        // An abstract or interface method has no braces, so nothing to compare.
        if (isset($tokens[$functionPtr]['scope_opener'], $tokens[$functionPtr]['scope_closer']) === false) {
            return false;
        }

        foreach ($tokens[$functionPtr]['conditions'] as $conditionCode) {
            if ($conditionCode === T_FUNCTION) {
                return false;
            }
        }

        return true;
    }

    /**
     * The normalized token stream of a declaration's body, and the number of
     * statements it holds.
     *
     * Both are produced in the one forward pass over the body, so the whole
     * file costs a single walk of its tokens.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{string, int}
     */
    private function summarizeBody(array $tokens, int $functionPtr): array
    {
        $bodyEnd = $tokens[$functionPtr]['scope_closer'];
        $stream = '';
        $statements = 0;
        $forHeaderEnd = 0;

        for ($ptr = ($tokens[$functionPtr]['scope_opener'] + 1); $ptr < $bodyEnd; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $content = $tokens[$ptr]['content'];
            $stream .= $code . ':' . strlen($content) . ':' . $content;

            if ($code === T_FOR && isset($tokens[$ptr]['parenthesis_closer']) === true) {
                $forHeaderEnd = $tokens[$ptr]['parenthesis_closer'];

                continue;
            }

            if ($code === T_SEMICOLON && $ptr > $forHeaderEnd) {
                $statements++;
            }
        }

        return [$stream, $statements];
    }

    /**
     * Names a declaration for the diagnostic: "chargeCard()".
     *
     * Only declarations that reached isComparableDeclaration() are described,
     * and a T_FUNCTION carrying a body always parsed a name, so
     * getDeclarationName() never answers null here.
     */
    private function describe(File $phpcsFile, int $functionPtr): string
    {
        return $phpcsFile->getDeclarationName($functionPtr) . '()';
    }
}

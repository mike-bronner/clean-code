<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowChainedPropertyFetchSniff implements Sniff
{
    private const GROUP_PRECEDERS = [
        // Where an expression starts: the file's own opening tags, the end of
        // the statement before, and the brace opening a block or a braced
        // member name. The matching closers are absent — see the method.
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_GOTO_LABEL,

        // Openers and separators inside an expression: an argument list, a
        // subscript, an array literal, and the punctuation between their
        // elements or a ternary's arms.
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_COMMA,
        T_COLON,
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_FN_ARROW,
        T_MATCH_ARROW,

        // Keywords that take an expression without parenthesising it.
        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
        T_CASE,
        T_CLONE,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,

        // Keywords that take a bare statement, which an expression may be.
        T_ELSE,
        T_DO,

        // Prefix operators PHP_CodeSniffer's own unions leave out.
        T_BOOLEAN_NOT,
        T_BITWISE_NOT,
        T_ASPERAND,
        T_ELLIPSIS,

        // Concatenation, the one binary operator absent from those unions.
        T_STRING_CONCAT,

        // PHP 8.5's two new tokens. PHP_CodeSniffer 3.13.6 predates both, so
        // neither reaches the unions above however well it fits one of them —
        // Tokens::$castTokens has no T_VOID_CAST and Tokens::$operators no
        // T_PIPE. CleanCode/Support/BackportedTokens.php records how the pair
        // was measured; each is admitted on its own evidence:
        //
        // - `(void)` is a cast, and a cast takes an expression, so a
        //   parenthesis after one opens a group.
        //   `<?php (void) ($book)->author->name;` passes `php -l` on PHP 8.5,
        //   and PHP_CodeSniffer tokenises it T_VOID_CAST, T_OPEN_PARENTHESIS,
        //   T_VARIABLE — the grouped-root shape this sniff reports. Refusing it
        //   would lose that report on 8.5 and keep it on 8.4.
        // - `|>` is a binary operator whose right operand is an expression
        //   evaluating to a callable. `<?php $r = $y |> ($this->resolver)->handler;`
        //   passes `php -l` on PHP 8.5 and tokenises T_PIPE,
        //   T_OPEN_PARENTHESIS, T_VARIABLE, so the parenthesis groups exactly
        //   as it does after `.` above.
        T_VOID_CAST,
        T_PIPE,
    ];

    private ?string $rootsKey = null;

    private array $roots = [];

    private array $cacheCounts = [
        'roots.builds' => 0,
        'roots.hits' => 0,
    ];

    private int $walkSteps = 0;

    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function walkSteps(): int
    {
        return $this->walkSteps;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $this->propertyNameAfter($phpcsFile, $stackPtr);

        if ($memberPtr === false) {
            return;
        }

        // The receiver of this hop has to be the member of a preceding hop for
        // the two to be consecutive. Anything else — a variable, a call's
        // closing parenthesis, an array subscript — starts a fresh segment.
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $receiverPtr === false
            || $tokens[$receiverPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $previousOperatorPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($receiverPtr - 1),
            null,
            true
        );

        if (
            $previousOperatorPtr === false
            || $this->isObjectOperator($tokens, $previousOperatorPtr) === false
        ) {
            return;
        }

        // One diagnostic per chain: a third hop's receiver is preceded by an
        // object operator too, which means the pair before it was already
        // reported.
        //
        // Asked before the root walk, never after. This test reads a fixed two
        // tokens where the walk reads the whole receiver expression, so putting
        // it first keeps an already-reported hop from starting a walk at all.
        // What actually bounds the cost of the walk is rootFrom()'s record —
        // this ordering is a constant-factor gain on top of it, and the two are
        // measured apart in the sniff's linear-time test.
        if ($this->isPrecededByAnotherHop($phpcsFile, $previousOperatorPtr) === true) {
            return;
        }

        if ($this->isRootedInVariable($phpcsFile, $previousOperatorPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Chained property fetch %s; expose the value as an accessor attribute on the '
                . 'first model instead (e.g. getAuthorNameAttribute() so callers read '
                . '$book->authorName rather than $book->author->name) '
                . '(see docs/standards/models-relationship-properties.md)',
            $memberPtr,
            'Found',
            // The operator is quoted from the matched token rather than written
            // out, so a nullsafe hop reads back as the source wrote it
            // (author?->name) instead of being reported as author->name.
            [
                $tokens[$receiverPtr]['content']
                    . $tokens[$stackPtr]['content']
                    . $tokens[$memberPtr]['content'],
            ]
        );
    }

    private function propertyNameAfter(File $phpcsFile, int $operatorPtr): int|false
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if (
            $memberPtr === false
            || $tokens[$memberPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if (
            $afterMemberPtr !== false
            && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS
        ) {
            return false;
        }

        return $memberPtr;
    }

    private function isRootedInVariable(File $phpcsFile, int $operatorPtr): bool
    {
        return $this->rootBefore($phpcsFile, $operatorPtr) !== false;
    }

    private function rootBefore(File $phpcsFile, int $beforePtr): int|false
    {
        return $this->rootFrom(
            $phpcsFile,
            $phpcsFile->findPrevious(Tokens::$emptyTokens, ($beforePtr - 1), null, true)
        );
    }

    private function rootFrom(File $phpcsFile, int|false $ptr): int|false
    {
        $this->discardRootsOfOtherStreams($phpcsFile);

        $tokens = $phpcsFile->getTokens();
        $walked = [];

        while ($ptr !== false) {
            if (array_key_exists($ptr, $this->roots) === true) {
                return $this->recordRoots($walked, $this->roots[$ptr]);
            }

            ++$this->walkSteps;
            $walked[] = $ptr;
            $code = $tokens[$ptr]['code'];

            if (
                $code === T_CLOSE_PARENTHESIS
                || $code === T_CLOSE_SQUARE_BRACKET
                || $code === T_CLOSE_CURLY_BRACKET
            ) {
                $openerPtr = $this->openerOf($tokens, $ptr);

                if ($openerPtr === false) {
                    return $this->recordRoots($walked, false);
                }

                $beforeOpenerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

                // Braces reached from here are a braced member name and
                // nothing else — $a->{$b}->c, whose opener follows the hop's
                // own operator. Every other brace pair that can sit in front
                // of an operator closes a body, not a receiver: a match's arms
                // (match ($book) { ... }->author->name), a closure's, an
                // anonymous class's. None has a single root to walk to, so the
                // walk stops rather than reading one out of the subject in
                // front of the body.
                if ($code === T_CLOSE_CURLY_BRACKET) {
                    if (
                        $beforeOpenerPtr === false
                        || $this->isObjectOperator($tokens, $beforeOpenerPtr) === false
                    ) {
                        return $this->recordRoots($walked, false);
                    }

                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                // A call's argument list and a subscript both belong to the
                // token in front of their opener, so the walk continues there.
                if (
                    $code === T_CLOSE_SQUARE_BRACKET
                    || $this->isInvokedOn($tokens, $beforeOpenerPtr) === true
                ) {
                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                // A grouping parenthesis belongs to nothing in front of it —
                // ($a)->b->c — and holds its own root, so the walk continues
                // inside the group. Anything else opening a parenthesis is a
                // construct this sniff does not model, and is refused.
                if ($this->isGroupingParenthesis($tokens, $beforeOpenerPtr) === false) {
                    return $this->recordRoots($walked, false);
                }

                return $this->recordRoots($walked, $this->rootInsideGroup($phpcsFile, $openerPtr, $ptr));
            }

            // Landing straight on an operator means the group just stepped
            // over was a braced member name ($a->{$b}); step over its hop too.
            if ($this->isObjectOperator($tokens, $ptr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                continue;
            }

            if (
                $code !== T_STRING
                && $code !== T_VARIABLE
            ) {
                return $this->recordRoots($walked, false);
            }

            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if (
                $previousPtr !== false
                && $this->isObjectOperator($tokens, $previousPtr) === true
            ) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previousPtr - 1), null, true);

                continue;
            }

            if (
                $previousPtr !== false
                && $tokens[$previousPtr]['code'] === T_DOUBLE_COLON
            ) {
                return $this->recordRoots($walked, false);
            }

            return $this->recordRoots($walked, $code === T_VARIABLE ? $ptr : false);
        }

        return $this->recordRoots($walked, false);
    }

    private function discardRootsOfOtherStreams(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;

        $key = $tokenStreams->key($phpcsFile);

        if ($this->rootsKey === $key) {
            $this->cacheCounts['roots.hits']++;

            return;
        }

        $this->cacheCounts['roots.builds']++;
        $this->rootsKey = $key;
        $this->roots = [];
        $this->walkSteps = 0;
    }

    private function recordRoots(array $walked, int|false $result): int|false
    {
        foreach ($walked as $ptr) {
            $this->roots[$ptr] = $result;
        }

        return $result;
    }

    private function rootInsideGroup(File $phpcsFile, int $openerPtr, int $closerPtr): int|false
    {
        $rootPtr = $this->rootBefore($phpcsFile, $closerPtr);

        if ($rootPtr === false) {
            return false;
        }

        $firstInGroupPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closerPtr, true);

        return $rootPtr === $firstInGroupPtr ? $openerPtr : false;
    }

    private function isPrecededByAnotherHop(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operatorPtr - 1), null, true);

        if (
            $receiverPtr === false
            || $tokens[$receiverPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($receiverPtr - 1), null, true);

        return $previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true;
    }

    private function isObjectOperator(array $tokens, int $ptr): bool
    {
        return $tokens[$ptr]['code'] === T_OBJECT_OPERATOR
            || $tokens[$ptr]['code'] === T_NULLSAFE_OBJECT_OPERATOR;
    }

    private function isInvokedOn(array $tokens, int|false $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return false;
        }

        return in_array(
            $tokens[$beforeOpenerPtr]['code'],
            [
                T_STRING,
                T_VARIABLE,
                T_CLOSE_PARENTHESIS,
                T_CLOSE_SQUARE_BRACKET,
                T_CLOSE_CURLY_BRACKET,
            ],
            true
        );
    }

    private function isGroupingParenthesis(array $tokens, int|false $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return true;
        }

        $code = $tokens[$beforeOpenerPtr]['code'];

        return in_array($code, self::GROUP_PRECEDERS, true)
            || isset(Tokens::$assignmentTokens[$code]) === true
            || isset(Tokens::$operators[$code]) === true
            || isset(Tokens::$comparisonTokens[$code]) === true
            || isset(Tokens::$booleanOperators[$code]) === true
            || isset(Tokens::$castTokens[$code]) === true;
    }

    private function openerOf(array $tokens, int $ptr): int|false
    {
        if ($tokens[$ptr]['code'] === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? false;
        }

        return $tokens[$ptr]['bracket_opener'] ?? false;
    }
}

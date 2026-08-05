<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags chained property fetches rooted in a variable ($a->b->c).
 *
 * Enforces the "Models: Relationship Properties" standard
 * (docs/standards/models-relationship-properties.md): reading through a
 * relationship ($book->author->name) couples the caller to two models and
 * leaves every call site to guard against a missing relationship. The remedy
 * is an accessor attribute on the first model — getAuthorNameAttribute() —
 * so the caller writes $book->authorName instead.
 *
 * Two or more consecutive plain property-fetch hops are the token-visible
 * shape of that traversal. A method-call hop is a different access pattern,
 * so it ends the segment it belongs to; property fetches after it start a new
 * segment and are judged on their own ($a->b()->c->d flags c->d). A dynamic
 * member name ($a->{$b}, $a->$b) is unknowable at token level and ends its
 * segment the same way. Array access and static roots (Foo::bar()->baz->qux)
 * are out of scope. A grouping parenthesis around the root hides nothing —
 * ($book)->author->name reads the same relationship as $book->author->name,
 * so the root is looked for inside the group.
 */
class DisallowChainedPropertyFetchSniff implements Sniff
{
    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
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

        if ($receiverPtr === false || $tokens[$receiverPtr]['code'] !== T_STRING) {
            return;
        }

        $previousOperatorPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($receiverPtr - 1),
            null,
            true
        );

        if ($previousOperatorPtr === false || $this->isObjectOperator($tokens, $previousOperatorPtr) === false) {
            return;
        }

        if ($this->isRootedInVariable($phpcsFile, $previousOperatorPtr) === false) {
            return;
        }

        // One diagnostic per chain: a third hop's receiver is preceded by an
        // object operator too, which means the pair before it was already
        // reported.
        if ($this->isPrecededByAnotherHop($phpcsFile, $previousOperatorPtr) === true) {
            return;
        }

        $phpcsFile->addError(
            'Chained property fetch %s; expose the value as an accessor attribute on the '
                . 'first model instead (e.g. getAuthorNameAttribute() so callers read '
                . '$book->authorName rather than $book->author->name) '
                . '(see docs/standards/models-relationship-properties.md)',
            $memberPtr,
            'Found',
            [$tokens[$receiverPtr]['content'] . '->' . $tokens[$memberPtr]['content']]
        );
    }

    /**
     * The pointer to this hop's member name when the hop is a plain property
     * fetch, false otherwise. A dynamic member name ($a->{$b}, $a->$b) is not
     * a T_STRING, and a name followed by an opening parenthesis is a method
     * call rather than a property read.
     *
     * @return int|false
     */
    private function propertyNameAfter(File $phpcsFile, int $operatorPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if ($memberPtr === false || $tokens[$memberPtr]['code'] !== T_STRING) {
            return false;
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if ($afterMemberPtr !== false && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS) {
            return false;
        }

        return $memberPtr;
    }

    /**
     * Whether the expression the given hop hangs off ultimately starts at a
     * variable. Walks left over the whole receiver expression, stepping over
     * completed calls, subscripts and braced member names, so that
     * $a->b()->c->d is recognised as variable-rooted while
     * Foo::bar()->baz->qux is not. A grouping parenthesis is walked into
     * instead of over, since it is where the root of ($a)->b->c actually sits.
     */
    private function isRootedInVariable(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operatorPtr - 1), null, true);

        while ($ptr !== false) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_CLOSE_PARENTHESIS || $code === T_CLOSE_SQUARE_BRACKET || $code === T_CLOSE_CURLY_BRACKET) {
                $openerPtr = $this->openerOf($tokens, $ptr);

                if ($openerPtr === false) {
                    return false;
                }

                $beforeOpenerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

                // A call, a subscript and a braced member name all belong to
                // the token in front of their opener, so the walk continues
                // there. A grouping parenthesis belongs to nothing in front of
                // it — ($a)->b->c, ($cond ? $a : $b)->author->name — and holds
                // its own root, so the walk continues inside the group.
                if ($code === T_CLOSE_PARENTHESIS && $this->isInvokedOn($tokens, $beforeOpenerPtr) === false) {
                    $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), ($openerPtr + 1), true);

                    continue;
                }

                $ptr = $beforeOpenerPtr;

                continue;
            }

            // Landing straight on an operator means the group just stepped
            // over was a braced member name ($a->{$b}); step over its hop too.
            if ($this->isObjectOperator($tokens, $ptr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                continue;
            }

            if ($code !== T_STRING && $code !== T_VARIABLE) {
                return false;
            }

            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previousPtr - 1), null, true);

                continue;
            }

            if ($previousPtr !== false && $tokens[$previousPtr]['code'] === T_DOUBLE_COLON) {
                return false;
            }

            return $code === T_VARIABLE;
        }

        return false;
    }

    /**
     * Whether the hop before the given one is itself a property-fetch hop,
     * which means this chain already produced a diagnostic further left.
     */
    private function isPrecededByAnotherHop(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operatorPtr - 1), null, true);

        if ($receiverPtr === false || $tokens[$receiverPtr]['code'] !== T_STRING) {
            return false;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($receiverPtr - 1), null, true);

        return $previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true;
    }

    /**
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isObjectOperator(array $tokens, int $ptr): bool
    {
        return $tokens[$ptr]['code'] === T_OBJECT_OPERATOR
            || $tokens[$ptr]['code'] === T_NULLSAFE_OBJECT_OPERATOR;
    }

    /**
     * Whether an opening parenthesis is an argument list belonging to what
     * precedes it, rather than a grouping parenthesis standing on its own.
     * Decided from the preceding token: only a token that ends an expression
     * can be called, and each listed here is one — a name (foo()), a variable
     * ($fn()), another call (foo()()), a subscript ($handlers['x']()) or a
     * braced member name ($book->{$method}()). Anything else in that position
     * (=, ?, :, return, another opener) cannot be, so the parenthesis groups.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param int|false                        $beforeOpenerPtr pointer to the
     *        token before the opener, false when the opener starts the file
     */
    private function isInvokedOn(array $tokens, $beforeOpenerPtr): bool
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

    /**
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return int|false
     */
    private function openerOf(array $tokens, int $ptr)
    {
        if ($tokens[$ptr]['code'] === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? false;
        }

        return $tokens[$ptr]['bracket_opener'] ?? false;
    }
}

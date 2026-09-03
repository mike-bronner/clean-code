<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DuplicatedArrayKeySniff implements Sniff
{
    private const TWO_POW_63 = 9223372036854775808.0;

    private const TWO_POW_64 = 18446744073709551616.0;

    private const NESTED_OPENERS = [
        T_OPEN_PARENTHESIS => 'parenthesis_closer',
        T_OPEN_SQUARE_BRACKET => 'bracket_closer',
        T_OPEN_SHORT_ARRAY => 'bracket_closer',
        T_OPEN_CURLY_BRACKET => 'bracket_closer',
    ];

    public function register(): array
    {
        return [T_ARRAY, T_OPEN_SHORT_ARRAY];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $bounds = $this->arrayBounds($tokens, $stackPtr);

        if ($bounds === null) {
            return;
        }

        [$openerPtr, $closerPtr] = $bounds;

        $seen = [];
        $elementStartPtr = ($openerPtr + 1);
        $arrowPtr = null;
        $ptr = $elementStartPtr;

        while ($ptr < $closerPtr) {
            $code = $tokens[$ptr]['code'];

            if (isset(self::NESTED_OPENERS[$code]) === true) {
                $nestedCloserPtr = $tokens[$ptr][self::NESTED_OPENERS[$code]] ?? null;

                // An unterminated construct in a file being edited has no
                // closer to jump to, and the walk cannot tell where the
                // element it opened ends. Abandoning the array is the safe
                // direction: reading on would take the commas inside the
                // unterminated construct for element separators and report
                // its contents as duplicates of the elements around it.
                if ($nestedCloserPtr === null) {
                    return;
                }

                $ptr = ($nestedCloserPtr + 1);

                continue;
            }

            // The `=>` of an element separates its key from its value. An
            // arrow function's arrow is T_FN_ARROW, not T_DOUBLE_ARROW, and
            // every other construct that uses `=>` — a nested array, a match
            // expression — is jumped over above, so valid source cannot put a
            // second one here. The first-wins tie-break is what malformed
            // source falls back on, and it keeps the key the span before the
            // earliest `=>` rather than an ever-widening one.
            if (
                $code === T_DOUBLE_ARROW
                && $arrowPtr === null
            ) {
                $arrowPtr = $ptr;
            }

            if ($code === T_COMMA) {
                $this->recordKey($phpcsFile, $seen, $elementStartPtr, $arrowPtr);
                $elementStartPtr = ($ptr + 1);
                $arrowPtr = null;
            }

            $ptr++;
        }

        // The last element, which a trailing comma may or may not follow.
        $this->recordKey($phpcsFile, $seen, $elementStartPtr, $arrowPtr);
    }

    private function arrayBounds(array $tokens, int $stackPtr): ?array
    {
        $isLongForm = $tokens[$stackPtr]['code'] === T_ARRAY;
        $openerPtr = $isLongForm ? $tokens[$stackPtr]['parenthesis_opener'] : $stackPtr;
        $closerPtr = $isLongForm
            ? ($tokens[$stackPtr]['parenthesis_closer'] ?? null)
            : ($tokens[$stackPtr]['bracket_closer'] ?? null);

        return $closerPtr === null ? null : [$openerPtr, $closerPtr];
    }

    private function recordKey(File $phpcsFile, array &$seen, int $elementStartPtr, ?int $arrowPtr): void
    {
        // No `=>` in the element: an implicit key, which no literal names.
        if ($arrowPtr === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $keyPtrs = $this->significantTokens($tokens, $elementStartPtr, $arrowPtr);
        $key = $this->keyValue($tokens, $keyPtrs);

        if ($key === null) {
            return;
        }

        if (isset($seen[$key]) === true) {
            $phpcsFile->addError(
                'Duplicate array key %s overrides the entry on line %d; remove one of them',
                $keyPtrs[0],
                'Found',
                [$this->describeKey($key), $seen[$key]]
            );

            return;
        }

        $seen[$key] = $tokens[$keyPtrs[0]]['line'];
    }

    private function keyValue(array $tokens, array $keyPtrs): int|string|null
    {
        $isNegated = count($keyPtrs) === 2 && $tokens[$keyPtrs[0]]['code'] === T_MINUS;

        if (
            count($keyPtrs) !== 1
            && $isNegated === false
        ) {
            return null;
        }

        $token = $tokens[$isNegated === true ? $keyPtrs[1] : $keyPtrs[0]];

        if (
            $isNegated === true
            && $token['code'] === T_DNUMBER
        ) {
            // A negated float carries its sign into the resolution instead of
            // being negated after it. The wrap can land on PHP_INT_MIN, whose
            // negation is not an integer at all, and coercing that back to a
            // key performs the very out-of-range cast floatValue() exists to
            // avoid — which aborts the whole file on 8.4 and 8.5 alike.
            $value = $this->floatValue('-' . $token['content']);
        } else {
            $value = $this->literalValue($token);

            // Only a number can be negated into a key, so a minus in front of
            // anything else means the key is not a literal after all.
            if ($isNegated === true) {
                $value = is_int($value) === true ? -$value : null;
            }
        }

        return $value === null ? null : array_key_first([$value => null]);
    }

    private function literalValue(array $token): int|string|null
    {
        return match ($token['code']) {
            T_LNUMBER => $this->integerValue($token['content']),
            T_DNUMBER => $this->floatValue($token['content']),
            T_TRUE => 1,
            T_FALSE => 0,
            T_NULL => '',
            T_CONSTANT_ENCAPSED_STRING => $this->stringValue($token['content']),
            default => null,
        };
    }

    private function integerValue(string $literal): ?int
    {
        $digits = str_replace('_', '', $literal);
        $prefix = strtolower(substr($digits, 0, 2));

        [$body, $base, $legalDigits] = match (true) {
            $prefix === '0x' => [substr($digits, 2), 16, '0-9A-Fa-f'],
            $prefix === '0b' => [substr($digits, 2), 2, '01'],
            $prefix === '0o' => [substr($digits, 2), 8, '0-7'],
            strlen($digits) > 1 && $digits[0] === '0' => [substr($digits, 1), 8, '0-7'],
            default => [$digits, 10, '0-9'],
        };

        if (preg_match('/^[' . $legalDigits . ']+$/', $body) !== 1) {
            return null;
        }

        return (int) match ($base) {
            16 => hexdec($body),
            8 => octdec($body),
            2 => bindec($body),
            default => $body,
        };
    }

    private function floatValue(string $literal): ?int
    {
        $digits = str_replace('_', '', $literal);

        if ($this->isNonDecimal(ltrim($digits, '-')) === true) {
            return null;
        }

        $value = (float) $digits;

        if (is_finite($value) === false) {
            return null;
        }

        if (
            $value >= -self::TWO_POW_63
            && $value < self::TWO_POW_63
        ) {
            return (int) $value;
        }

        $wrapped = fmod($value, self::TWO_POW_64);

        if ($wrapped < 0.0) {
            $wrapped += self::TWO_POW_64;
        }

        if ($wrapped >= self::TWO_POW_63) {
            $wrapped -= self::TWO_POW_64;
        }

        return (int) $wrapped;
    }

    private function isNonDecimal(string $digits): bool
    {
        return preg_match('/^0([xXbBoO]|[0-7]+$)/', $digits) === 1;
    }

    private function stringValue(string $literal): ?string
    {
        $inner = substr($literal, 1, -1);

        // Audited, unguarded on purpose: a failed read returns null, and null
        // is already this method's "the token stream does not settle it"
        // sentinel, which the caller reads as "skip this key". Safe by
        // circumstance rather than by construction, so it is written down here:
        // change that sentinel and this call needs a guard of its own. The
        // pattern is a literal backslash followed by a two-member character
        // class, with no quantifier, no recursion and no `/u` modifier, so
        // nothing is known to drive it there in the first place.
        if ($literal[0] === "'") {
            return preg_replace('/\\\\([\\\\\'])/', '$1', $inner);
        }

        return str_contains($inner, '\\') === true ? null : $inner;
    }

    private function describeKey(int|string $key): string
    {
        return is_int($key) === true ? (string) $key : "'" . $key . "'";
    }

    private function significantTokens(array $tokens, int $startPtr, int $endPtr): array
    {
        $ptrs = [];

        for ($ptr = $startPtr; $ptr < $endPtr; $ptr++) {
            if (isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === false) {
                $ptrs[] = $ptr;
            }
        }

        return $ptrs;
    }
}

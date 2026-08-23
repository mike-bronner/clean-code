<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Arrays;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's Clean Code rule `DuplicatedArrayKey`: an array literal
 * that declares the same key twice. The later entry wins at runtime and the
 * earlier one is dead code, so one of the two is always a mistake.
 *
 * No PHPCS, Generic, Squiz, or Slevomat sniff covers this. `Squiz.Arrays.
 * ArrayDeclaration` is the only bundled sniff that looks at array keys at all,
 * and every code it emits is about layout — whether a key is present, and how
 * keys, arrows, and commas are spaced and aligned — never about two keys
 * naming the same slot. So this is a custom sniff. The mapping and the
 * differences from PHPMD are set out in
 * docs/phpmd/cleancode-duplicatedarraykey.md.
 *
 * Two keys are the same when PHP would store them under the same slot, not
 * when they are spelled the same way. PHP coerces every array key to an int or
 * a string on insertion — `false` and `'0'` are the int key 0, `true` is 1,
 * `null` is the empty string, `'1'` is the int key 1, and every integer base
 * names the same number — so the literal is resolved to its value and that
 * value is used as a key in turn, which applies PHP's own coercion rather than
 * a re-implementation of it.
 *
 * Only a key the sniff can resolve with certainty is compared. A constant, a
 * class constant, a variable, an expression, an interpolated string, and a
 * double-quoted string carrying an escape sequence are all skipped: their
 * values are not in the token stream, and reporting on a guess would be worse
 * than staying silent. An implicit (auto-incrementing) key is skipped for the
 * same reason — no literal in the source names it, since its value depends on
 * every element before it. PHPMD skips all of these too.
 *
 * Detection only. Deleting the overridden entry looks mechanical but is not:
 * its value can carry a side effect (`0 => register($handler)`), and which of
 * the two entries is the mistake is a judgement about intent — the key may be
 * the typo rather than the duplication. PHPMD reports rather than rewrites for
 * the same reason.
 */
class DuplicatedArrayKeySniff implements Sniff
{
    /**
     * 2**63 and 2**64 as floats, the two bounds floatValue() folds a
     * non-representable float key against. Both are exact in a double —
     * a power of two always is — so neither bound is approximate.
     */
    private const TWO_POW_63 = 9223372036854775808.0;

    private const TWO_POW_64 = 18446744073709551616.0;

    /**
     * Constructs that can nest inside an array element, mapped to the token
     * index holding their closer. The element walk jumps over each one whole,
     * so a comma or a `=>` belonging to a nested construct is never mistaken
     * for the array's own element separator or key separator.
     *
     * @var array<int|string, string>
     */
    private const NESTED_OPENERS = [
        T_OPEN_PARENTHESIS => 'parenthesis_closer',
        T_OPEN_SQUARE_BRACKET => 'bracket_closer',
        T_OPEN_SHORT_ARRAY => 'bracket_closer',
        T_OPEN_CURLY_BRACKET => 'bracket_closer',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_ARRAY, T_OPEN_SHORT_ARRAY];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
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
            if ($code === T_DOUBLE_ARROW && $arrowPtr === null) {
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

    /**
     * The opener and closer of the array literal at $stackPtr, or null when it
     * has no closer to walk to.
     *
     * Only the long form can reach that null: an unterminated `array(` keeps
     * its T_ARRAY token and its opener but gains no `parenthesis_closer`,
     * whereas PHP_CodeSniffer labels an unterminated `[` T_OPEN_SQUARE_BRACKET
     * rather than T_OPEN_SHORT_ARRAY, so this sniff never registers on it. The
     * short-array half of the lookup is written defensively all the same,
     * because the alternative is an undefined-index warning in a linter run
     * over a file someone is halfway through typing.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{int, int}|null
     */
    private function arrayBounds(array $tokens, int $stackPtr): ?array
    {
        $isLongForm = $tokens[$stackPtr]['code'] === T_ARRAY;
        $openerPtr = $isLongForm ? $tokens[$stackPtr]['parenthesis_opener'] : $stackPtr;
        $closerPtr = $isLongForm
            ? ($tokens[$stackPtr]['parenthesis_closer'] ?? null)
            : ($tokens[$stackPtr]['bracket_closer'] ?? null);

        return $closerPtr === null ? null : [$openerPtr, $closerPtr];
    }

    /**
     * Resolves one element's key and either records it as the first
     * declaration or reports it as a duplicate of one.
     *
     * The first declaration is kept and the duplicate discarded, so a key
     * written three times is reported twice, each time against the same first
     * declaration. That matches PHPMD, and it keeps the diagnostic pointing at
     * the entry that survives at runtime.
     *
     * @param array<int|string, int> $seen Key => the line it was first declared on.
     */
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

    /**
     * The array key the given tokens name, or null when they are not a literal
     * this sniff resolves.
     *
     * The value is round-tripped through a one-element array so that PHP's own
     * key coercion decides the result: `'1'` becomes the int key 1 while `'01'`
     * stays a string, which is the rule this sniff would otherwise have to
     * restate. Comparing the returned keys is then the same as comparing the
     * slots PHP would store the entries in.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, int>                  $keyPtrs
     */
    private function keyValue(array $tokens, array $keyPtrs): int|string|null
    {
        $isNegated = count($keyPtrs) === 2 && $tokens[$keyPtrs[0]]['code'] === T_MINUS;

        if (count($keyPtrs) !== 1 && $isNegated === false) {
            return null;
        }

        $value = $this->literalValue($tokens[$isNegated === true ? $keyPtrs[1] : $keyPtrs[0]]);

        // Only a number can be negated into a key, so a minus in front of
        // anything else means the key is not a literal after all.
        if ($isNegated === true) {
            $value = is_int($value) === true ? -$value : null;
        }

        return $value === null ? null : array_key_first([$value => null]);
    }

    /**
     * The value of a single literal token, or null when the token is not a
     * literal whose value the token stream settles.
     *
     * @param array<string, mixed> $token
     */
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

    /**
     * The value of an integer literal, in any base PHP accepts.
     *
     * No overflow case is needed: PHP tokenises a numeric literal too large for
     * the integer range as a float whatever its base — `9223372036854775808`,
     * `0xFFFFFFFFFFFFFFFFF`, and their octal and binary counterparts all arrive
     * as T_DNUMBER — so a T_LNUMBER always fits, and floatValue() handles the
     * rest.
     */
    private function integerValue(string $literal): int
    {
        $digits = str_replace('_', '', $literal);
        $prefix = strtolower(substr($digits, 0, 2));

        return (int) match (true) {
            $prefix === '0x' => hexdec(substr($digits, 2)),
            $prefix === '0b' => bindec(substr($digits, 2)),
            $prefix === '0o' => octdec(substr($digits, 2)),
            strlen($digits) > 1 && $digits[0] === '0' => octdec(substr($digits, 1)),
            default => $digits,
        };
    }

    /**
     * The integer key a float literal lands on. PHP truncates a float key
     * toward zero, which is what the cast does.
     *
     * A literal too large to be finite (`1e400`) has no defined integer form,
     * so it is left unresolved rather than folded onto whatever `(int) INF`
     * happens to produce.
     *
     * A finite literal outside the integer range does have a defined form —
     * PHP wraps it modulo 2**64 and uses the result as the key, which is why
     * `[9223372036854775808 => 'a', 9223372036854775808 => 'b']` is a genuine
     * duplicate on key -9223372036854775808. The cast that computes it is the
     * problem: PHP 8.5 raises `The float ... is not representable as an int,
     * cast occurred` as an E_WARNING, PHP_CodeSniffer turns every warning
     * raised inside a sniff into an exception, and the run aborts with
     * Internal.Exception instead of reporting the duplicate. Suppressing the
     * warning is not available either — the master ruleset carries
     * Generic.PHP.NoSilencedErrors, and PHP_CodeSniffer's handler ignores
     * error_reporting() in any case.
     *
     * So the wrap is done here, in arithmetic that raises nothing, and only for
     * the literals that would warn: anything already inside the integer range
     * is cast directly, exactly as before. The three steps are PHP's own
     * zend_dval_to_lval() — take the value modulo 2**64, lift a negative
     * remainder into [0, 2**64), then fold the top half down into the signed
     * range — and they reproduce the cast rather than approximate it, which is
     * asserted against the interpreter itself in DuplicatedArrayKeyTest.
     */
    private function floatValue(string $literal): ?int
    {
        $value = (float) str_replace('_', '', $literal);

        if (is_finite($value) === false) {
            return null;
        }

        if ($value >= -self::TWO_POW_63 && $value < self::TWO_POW_63) {
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

    /**
     * The value of a quoted string literal, or null when the token stream does
     * not settle it.
     *
     * A single-quoted string has exactly two escape sequences, both of which
     * stand for the character after the backslash. A double-quoted one has the
     * whole escape table — `\n`, `\x41`, `\u{1F600}`, `\101` — so a backslash
     * in it means the key is not the source text and resolving it would mean
     * re-implementing PHP's escaping. Those are skipped instead.
     */
    private function stringValue(string $literal): ?string
    {
        $inner = substr($literal, 1, -1);

        if ($literal[0] === "'") {
            return preg_replace('/\\\\([\\\\\'])/', '$1', $inner);
        }

        return str_contains($inner, '\\') === true ? null : $inner;
    }

    /**
     * The key as the message should show it: an integer bare, a string quoted.
     * The key has already been through PHP's coercion, so the message names the
     * slot both entries share rather than either entry's spelling.
     */
    private function describeKey(int|string $key): string
    {
        return is_int($key) === true ? (string) $key : "'" . $key . "'";
    }

    /**
     * The pointers to every non-whitespace, non-comment token in [$startPtr,
     * $endPtr).
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array<int, int>
     */
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

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags bare numeric literals used in expressions instead of named constants.
 *
 * Partial enforcement of the "Naming: Semantic naming principles" standard
 * (docs/standards/naming-semantic-naming-principles.md, #18): the "Use
 * Searchable Names" rule calls out numeric constants as hard to locate across
 * a body of text, and that one slice is decidable from a single file's tokens.
 * A bare 7 cannot be grepped for by meaning, and the same 7 elsewhere carries
 * no signal that it is the same concept; MAX_CLASSES_PER_STUDENT carries both.
 *
 * Declaration sites are where a literal is *being given* its name, so they are
 * skipped: const statements, class constant and enum case declarations, and
 * property and parameter default values. Attribute arguments are skipped too —
 * they are declarative metadata rather than an evaluated expression, and
 * skipping them keeps the result the same wherever the attribute sits, instead
 * of depending on whether its target happens to be inside a class body.
 *
 * Violations are warnings, not errors. Some literals really are self-evident
 * in context (simple arithmetic, a well-known port), so the sniff points at
 * naming candidates rather than mandating a rewrite. There is no fixer: only
 * the author can say what the number means, and inventing a constant name is
 * exactly the judgement the standard leaves to code review.
 */
class DisallowMagicNumbersSniff implements Sniff
{
    /**
     * Values idiomatic enough that naming them hides nothing — the empty
     * count, the single step, the not-found index. Configurable from a
     * ruleset via <property name="ignoredNumbers" type="array" .../>.
     *
     * Entries are compared by numeric value, not by spelling, so 0 also
     * covers 0.0 and 0x0.
     *
     * @var array<string>
     */
    public array $ignoredNumbers = [
        '0',
        '1',
        '-1',
    ];

    /**
     * Owners of a parenthesis whose contents name a value rather than compute
     * with one. A function, closure, or arrow function's parentheses are its
     * parameter list, reached by a literal only through a default value.
     * `declare()` is the same in the strongest form: PHP requires its
     * directive value to be a literal, so `declare(strict_types=1)` cannot be
     * rewritten to use a constant at all.
     */
    private const DECLARATIVE_PARENTHESES = [
        T_FUNCTION,
        T_CLOSURE,
        T_FN,
        T_DECLARE,
    ];

    /**
     * Class-like scopes whose direct body holds declarations only. A literal
     * sitting immediately inside one — not inside a method — can only be a
     * property default, a class constant value, or an enum case value.
     */
    private const DECLARATION_SCOPES = [
        T_CLASS,
        T_ANON_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
    ];

    /**
     * Tokens that can end an operand. A minus directly after one of these is
     * subtraction; a minus after anything else negates what follows.
     */
    private const OPERAND_ENDINGS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_STRING,
        T_CONSTANT_ENCAPSED_STRING,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_LNUMBER,
            T_DNUMBER,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isDeclarationSite($phpcsFile, $stackPtr) === true) {
            return;
        }

        $literal = $this->signedLiteral($phpcsFile, $stackPtr);

        if ($this->isIgnored($literal) === true) {
            return;
        }

        $phpcsFile->addWarning(
            'Magic number %s is not searchable; name it with a constant '
                . '(see docs/standards/naming-semantic-naming-principles.md)',
            $stackPtr,
            'Found',
            [$literal]
        );
    }

    /**
     * Whether the literal is being given a name rather than used.
     */
    private function isDeclarationSite(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['attribute_opener']) === true) {
            return true;
        }

        $conditions = $tokens[$stackPtr]['conditions'];

        if ($conditions !== [] && in_array(end($conditions), self::DECLARATION_SCOPES, true) === true) {
            return true;
        }

        if ($this->isInsideDeclarativeParentheses($phpcsFile, $stackPtr) === true) {
            return true;
        }

        return $this->isConstValue($phpcsFile, $stackPtr);
    }

    /**
     * Whether the literal sits inside a parameter list or a declare directive.
     *
     * Every enclosing parenthesis is checked, not just the innermost, so a
     * literal nested inside a new-in-initializer default
     * (`$fee = new Money(4500)`) is still recognised as a parameter default.
     */
    private function isInsideDeclarativeParentheses(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $opener) {
            if (isset($tokens[$opener]['parenthesis_owner']) === false) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'];

            if (in_array($tokens[$owner]['code'], self::DECLARATIVE_PARENTHESES, true) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the literal belongs to a `const` statement. Class and interface
     * constants are already covered by the class-scope check; this catches the
     * namespace-level `const RETRIES = 3;`, whose literal has no enclosing
     * scope at all. The search stops at the nearest statement boundary, so a
     * const declared earlier in the file cannot claim an unrelated literal.
     */
    private function isConstValue(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $statementStart = $phpcsFile->findPrevious(
            [
                T_CONST,
                T_SEMICOLON,
                T_OPEN_CURLY_BRACKET,
                T_CLOSE_CURLY_BRACKET,
                T_OPEN_TAG,
            ],
            ($stackPtr - 1)
        );

        return $statementStart !== false && $tokens[$statementStart]['code'] === T_CONST;
    }

    /**
     * The literal's own spelling, prefixed with a minus when the sign belongs
     * to it. PHP has no negative-number token, so -1 reaches the sniff as a
     * T_MINUS followed by 1, and the shipped ignore list could never match it
     * otherwise.
     */
    private function signedLiteral(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        $minusPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($minusPtr === false || $tokens[$minusPtr]['code'] !== T_MINUS) {
            return $content;
        }

        $beforeMinus = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($minusPtr - 1), null, true);

        if (
            $beforeMinus !== false
            && in_array($tokens[$beforeMinus]['code'], self::OPERAND_ENDINGS, true) === true
        ) {
            return $content;
        }

        return '-' . $content;
    }

    /**
     * Whether the literal's value appears in the ignore list. Comparison is by
     * value rather than by spelling, so an entry of 0 also silences 0.0 and
     * 0x0, and a hexadecimal literal is never mistaken for the decimal zero a
     * naive cast would produce.
     */
    private function isIgnored(string $literal): bool
    {
        $value = $this->numericValue($literal);

        foreach ($this->ignoredNumbers as $ignored) {
            if ($this->numericValue((string) $ignored) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The numeric value of a PHP numeric literal, in every base PHP accepts
     * and with readability underscores removed.
     */
    private function numericValue(string $literal): float
    {
        $literal = trim(str_replace('_', '', $literal));
        $sign = 1.0;

        if (str_starts_with($literal, '-') === true) {
            $sign = -1.0;
            $literal = substr($literal, 1);
        }

        $magnitude = match (true) {
            preg_match('/^0[xX][0-9a-fA-F]+$/', $literal) === 1 => (float) hexdec(substr($literal, 2)),
            preg_match('/^0[bB][01]+$/', $literal) === 1 => (float) bindec(substr($literal, 2)),
            preg_match('/^0[oO][0-7]+$/', $literal) === 1 => (float) octdec(substr($literal, 2)),
            preg_match('/^0[0-7]+$/', $literal) === 1 => (float) octdec($literal),
            default => (float) $literal,
        };

        return $sign * $magnitude;
    }
}

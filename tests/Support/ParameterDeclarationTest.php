<?php

/**
 * Tests MikeBronner\CleanCode\Support\ParameterDeclaration, the shared answer to
 * "does this class-scoped variable declare a property?" (issue #299).
 *
 * The class is exercised directly rather than through a sniff. Three sniffs
 * consume it — DisallowAlwaysOnEagerLoading, Superglobals and TooManyFields —
 * and each reaches it down a filter of its own, so a sniff-driven test would
 * only ever cover the shapes that sniff's own filter lets through. Every shape
 * below is a real answer the class has to give to one caller or another.
 *
 * The three parameterDeclaration* helpers the expectations below run on live in
 * tests/Helpers.php with every other fixture-driving helper.
 *
 * The sources are analysed rather than merely tokenised because PHP_CodeSniffer
 * populates 'nested_parenthesis' and 'parenthesis_owner' during the parse, and
 * those two keys are the whole resolution. The sniff the ruleset is narrowed to
 * is incidental: nothing here reads a violation, only the token stream the parse
 * leaves behind.
 */

declare(strict_types=1);

/**
 * A class holding one of every shape the resolution has to separate.
 *
 * Two variables are written twice on purpose, and the second occurrence is the
 * shape under test in both cases: $closureUse is a local first and a `use()`
 * clause variable second, and $methodLocal is a local first and a call argument
 * second. Every expectation below names the occurrence it means.
 */
const PARAMETER_DECLARATION_SUBJECT = <<<'PHP'
class Subject
{
    public array $bodyProperty = [];

    public function __construct(private int $promotedFirst, int $plainSecond)
    {
        $this->bodyProperty = [$plainSecond];
    }

    public function method(int $plainParameter): int
    {
        $methodLocal = $plainParameter;
        $closureUse = 1;
        $closure = function (int $closureParameter) use ($closureUse): int {
            return $closureParameter + $closureUse;
        };
        $arrow = fn (int $arrowParameter): int => $arrowParameter;

        return $closure(strlen((string) $methodLocal)) + $arrow(1);
    }
}
PHP;

/**
 * A property hook. PHP_CodeSniffer opens no scope for one, so a hook's own
 * parameter and every call argument written in a hook body reach the class as
 * their innermost condition, exactly as a real parameter list does.
 */
const PARAMETER_DECLARATION_HOOK_SUBJECT = <<<'PHP'
class Hooked
{
    public float $stored = 0.0;

    public float $reading {
        set (float $hookParameter) {
            $hookArgument = $hookParameter * 2.0;
            $this->stored = round($hookArgument);
        }
    }
}
PHP;

/**
 * A constructor whose parameter list is never closed — live coding. Deliberately
 * unparsable; the closing parenthesis and braces must never be added.
 */
const PARAMETER_DECLARATION_UNCLOSED_SUBJECT = <<<'PHP'
class HalfWritten
{
    public function __construct(private int $unclosedPromoted, int $unclosedPlain
PHP;

it('reads a property declared in the class body as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$bodyProperty'))->toBe([false, false]);
});

/**
 * The pair the whole class exists for: promoted and plain, adjacent in one
 * signature, sharing the class as their innermost condition. Reading either by
 * position alone gets the other one wrong.
 */
it('separates a promoted parameter from a plain one in the same signature', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$promotedFirst'))->toBe([false, true])
        ->and(parameterDeclarationAnswers($file, '$plainSecond'))->toBe([true, false]);
});

it('reads an ordinary method parameter as plain', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$plainParameter'))->toBe([true, false]);
});

it('reads a variable in a method body as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$methodLocal'))->toBe([false, false]);
});

/**
 * The shapes that put a variable inside parentheses a T_FUNCTION does not own.
 * PHP_CodeSniffer owns a closure's list with T_CLOSURE, an arrow function's with
 * T_FN, and a `use()` clause and an ordinary call with nothing at all. None of
 * the four can declare a property, so the owner test rejects all of them before
 * getMethodParameters() — which throws on a token that is not a function — is
 * ever reached.
 */
it('reads a closure or arrow-function parameter, a use() variable and a call argument as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$closureParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$arrowParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$closureUse', 2))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$methodLocal', 2))->toBe([false, false]);
});

/**
 * Why the two questions are published separately instead of one being read as
 * the negation of the other. A hook's parameter and a call argument in a hook
 * body are neither plain parameters nor promoted ones, so `! isPlainParameter()`
 * calls both of them promoted. TooManyFields counts a promoted parameter as a
 * declared field, and reading the negation there counts the nine such variables
 * in tests/fixtures/TooManyFieldsSniff/property-hooks.php as fields of
 * Temperature — which that fixture's own test in
 * tests/Standards/TooManyFieldsTest.php then fails on.
 */
it('reads a hook parameter and a hook-body call argument as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_HOOK_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$hookParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$hookArgument', 2))->toBe([false, false]);
});

/**
 * An unfinished signature. PHP_CodeSniffer records no 'nested_parenthesis' on a
 * token inside a pair it never matched, so both questions answer false from the
 * first guard, and getMethodParameters() — which would answer [] here, sending
 * the promoted parameter back as plain — is never consulted. The test asserts
 * the answers rather than the absence of an exception because a throw would fail
 * it either way.
 */
it('answers false for both questions on an unclosed parameter list', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_UNCLOSED_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$unclosedPromoted'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$unclosedPlain'))->toBe([false, false]);
});

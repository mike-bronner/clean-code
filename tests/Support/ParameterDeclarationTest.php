<?php

declare(strict_types=1);

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

const PARAMETER_DECLARATION_UNCLOSED_SUBJECT = <<<'PHP'
class HalfWritten
{
    public function __construct(private int $unclosedPromoted, int $unclosedPlain
PHP;

it('reads a property declared in the class body as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$bodyProperty'))->toBe([false, false]);
});

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

it('reads a closure or arrow-function parameter, a use() variable and a call argument as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$closureParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$arrowParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$closureUse', 2))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$methodLocal', 2))->toBe([false, false]);
});

it('reads a hook parameter and a hook-body call argument as neither', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_HOOK_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$hookParameter'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$hookArgument', 2))->toBe([false, false]);
});

it('answers false for both questions on an unclosed parameter list', function (): void {
    $file = parameterDeclarationFile(PARAMETER_DECLARATION_UNCLOSED_SUBJECT);

    expect(parameterDeclarationAnswers($file, '$unclosedPromoted'))->toBe([false, false])
        ->and(parameterDeclarationAnswers($file, '$unclosedPlain'))->toBe([false, false]);
});

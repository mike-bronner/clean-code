<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\EmptyClassBody;

it('answers true only for a class-like whose {} sits on its declaration line', function (): void {
    $file = parseFixture('EmptyClassBody', 'bodies.php');
    $emptyClassBody = new EmptyClassBody();
    $verdicts = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($pointer === ($token['scope_condition'] ?? null)) {
            $verdicts[] = [$token['line'], $token['type'], $emptyClassBody->isInline($file, $pointer)];
        }
    }

    expect($verdicts)->toBe([
        [5, 'T_CLASS', true],
        [7, 'T_INTERFACE', true],
        [9, 'T_TRAIT', true],
        [11, 'T_ENUM', true],
        [13, 'T_ANON_CLASS', true],
        [15, 'T_CLASS', true],
        [17, 'T_CLASS', false],
        [19, 'T_CLASS', false],
        [21, 'T_CLASS', false],
        [24, 'T_CLASS', false],
        [26, 'T_FUNCTION', false],
        [29, 'T_FUNCTION', false],
    ]);
});

it('answers false for a class-like with no body yet', function (): void {
    $file = parseFixture('Declarations', 'nameless.php');

    expect((new EmptyClassBody())->isInline($file, $file->findNext(T_CLASS, 0)))->toBeFalse();
});

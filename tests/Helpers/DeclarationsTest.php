<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\Declarations;

it('names every named declaration, and no closure or anonymous class', function (): void {
    $file = parseFixture('Declarations', 'declarations.php');
    $declarations = new Declarations();
    $declarationTokens = [T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_CLOSURE, T_ANON_CLASS];
    $names = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if (in_array($token['code'], $declarationTokens, true) === true) {
            $names[] = [$token['type'], $declarations->name($file, $pointer)];
        }
    }

    expect($names)->toBe([
        ['T_FUNCTION', 'namedFunction'],
        ['T_INTERFACE', 'NamedInterface'],
        ['T_TRAIT', 'NamedTrait'],
        ['T_ENUM', 'NamedEnum'],
        ['T_CLASS', 'NamedClass'],
        ['T_FUNCTION', 'namedMethod'],
        ['T_CLOSURE', null],
        ['T_ANON_CLASS', null],
    ]);
});

it('answers null for a declaration that has no name yet', function (): void {
    $file = parseFixture('Declarations', 'nameless.php');

    expect((new Declarations())->name($file, $file->findNext(T_CLASS, 0)))->toBeNull();
});

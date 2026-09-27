<?php

declare(strict_types=1);

const UNUSED_USES = 'SlevomatCodingStandard.Namespaces.UnusedUses';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_USES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_USES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each unused use individually at its own line', function (): void {
    $errors = analyzeFixture(UNUSED_USES, 'failing.php')->getErrors();

    expect(array_keys($errors))->toBe([7, 10]);

    foreach ([7, 10] as $line) {
        $lineErrors = array_merge(...array_values($errors[$line]));

        expect($lineErrors)->toHaveCount(1)
            ->and($lineErrors[0]['source'])->toBe(UNUSED_USES . '.UnusedUse')
            ->and($lineErrors[0]['fixable'])->toBeTrue();
    }
});

it('does not treat a use referenced only in a docblock as unused', function (): void {
    $file = analyzeFixture(UNUSED_USES, 'docblock-only.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('removes only the unused use statements when fixed', function (): void {
    $file = analyzeFixture(UNUSED_USES, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('UnusedUsesSniff', 'autofixed.php')));
});

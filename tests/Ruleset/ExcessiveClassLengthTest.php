<?php

declare(strict_types=1);

const EXCESSIVE_CLASS_LENGTH_RULE = 'CleanCode.Classes.ExcessiveClassLength';

it('ships PHPMD\'s own thresholds', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[EXCESSIVE_CLASS_LENGTH_RULE]];

    expect($sniff->minimum)->toBe(1000)
        ->and($sniff->ignoreWhitespace)->toBeFalse();
});

it('flags a 1000-line class through the whole ruleset', function (): void {
    $file = analyzeWithMasterRuleset(
            fixturePath('ExcessiveClassLengthSniff', 'failing.php')
        );

    $reports = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                if ($message['source'] === EXCESSIVE_CLASS_LENGTH_RULE . '.TooLong') {
                    $reports[$line][] = $message['message'];
                }
            }
        }
    }

    expect($reports)->toBe([
        11 => ['The class Colossus has 1000 lines of code. Current threshold is 1000. Avoid really long classes.'],
    ]);
});

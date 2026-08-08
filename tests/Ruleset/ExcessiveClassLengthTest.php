<?php

/**
 * The custom CleanCode.Classes.ExcessiveClassLength sniff *as wired into the
 * master rules.xml* (PHPMD CodeSize: ExcessiveClassLength, #93). The sniff's
 * own behaviour lives in tests/Standards/ExcessiveClassLengthTest.php; what is
 * asserted here is the shipped configuration, which is what decides whether a
 * consumer running `phpcs --standard=rules.xml` still has to run `phpmd`
 * separately for this rule.
 *
 * rules.xml writes PHPMD's own defaults out explicitly rather than leaning on
 * the sniff's property defaults, so the two can drift apart without anyone
 * noticing. These tests read the configured instance and then run a real
 * 1000-line class through the whole ruleset, so both halves are pinned.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Sniffs\Sniff;

const EXCESSIVE_CLASS_LENGTH_RULE = 'CleanCode.Classes.ExcessiveClassLength';

/**
 * The sniff instance the master ruleset built, with rules.xml's <properties>
 * already applied.
 */
function configuredExcessiveClassLengthSniff(): Sniff
{
    [, $ruleset] = buildRuleset();

    return $ruleset->sniffs[$ruleset->sniffCodes[EXCESSIVE_CLASS_LENGTH_RULE]];
}

/**
 * PHPMD's stock codesize.xml gives ExcessiveClassLength a `minimum` of 1000 and
 * an `ignore-whitespace` of false. rules.xml has to carry the same pair, or the
 * package quietly enforces a different rule from the one it documents.
 */
it('ships PHPMD\'s own thresholds', function (): void {
    $sniff = configuredExcessiveClassLengthSniff();

    expect($sniff->minimum)->toBe(1000)
        ->and($sniff->ignoreWhitespace)->toBeFalse();
});

/**
 * End to end through the master ruleset, every sniff active: a class of exactly
 * 1000 lines is reported once, at its declaration line, with PHPMD's own
 * message. The assertions are scoped to this rule's source so unrelated
 * additions to rules.xml — and the other violations this deliberately
 * repetitive fixture attracts — cannot break them.
 */
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

<?php

/**
 * End-to-end integration test for the "Arrays: Operator spacing & line breaks"
 * standard (#35). Unlike the per-sniff tests in tests/Rules and tests/Standards
 * — which narrow $ruleset->sniffs to one sniff before processing — this runs
 * the *entire* master rules.xml against one fixture and asserts every real
 * violation is reported exactly once.
 *
 * That whole-ruleset view is where operator diagnostics can duplicate:
 *   - the stricter Squiz.WhiteSpace.OperatorSpacing stacking on the "at least
 *     one space" PSR12.Operators.OperatorSpacing (excluded in rules.xml), and
 *   - CleanCode.Operators.OperatorLineBreak overlapping
 *     CleanCode.Conditionals.OneConditionPerLine on an operator dangling inside
 *     a wrapped condition (OperatorLineBreak defers there).
 * A per-sniff test structurally cannot catch either — only this one can.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Integration;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

class OperatorRulesIntegrationTest extends TestCase
{
    private static Ruleset $ruleset;

    private static Config $config;

    public static function setUpBeforeClass(): void
    {
        self::$config = new ConfigDouble(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);

        // The master ruleset references third-party standards; restore their
        // installed paths after ConfigDouble blanks the static config data.
        Config::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        self::$ruleset = new Ruleset(self::$config);
    }

    /**
     * The fixture holds one instance of each operator concern; every line below
     * carries exactly one diagnostic from exactly one sniff. Regressions this
     * pins by adding a second (or dropping the only) source:
     *   - re-stacking PSR12 on Squiz spacing (line 9/10),
     *   - re-doubling OperatorLineBreak on OneConditionPerLine's boolean (17),
     *   - re-adding "(" to NotOperatorSpacing so PSR12's ControlStructureSpacing
     *     double-reports "if ( ! " (line 23), and
     *   - re-broadening OperatorLineBreak's deferral so a dangling non-boolean
     *     operator inside a multi-condition slips through unreported (line 28).
     */
    public function testEveryOperatorViolationIsReportedExactlyOnce(): void
    {
        $file = new LocalFile(__DIR__ . '/fixtures/operator-rules.inc', self::$ruleset, self::$config);
        $file->process();

        $this->assertSame(
            [
                // exactly-1-space spacing — Squiz supersedes PSR12, no stacking
                9 => [
                    'Squiz.WhiteSpace.OperatorSpacing.NoSpaceAfter',
                    'Squiz.WhiteSpace.OperatorSpacing.NoSpaceBefore',
                ],
                // concatenation spacing — ConcatenationSpacing only, no PSR12
                10 => ['Squiz.Strings.ConcatenationSpacing.PaddingFound'],
                // padding before "=" — the ignoreSpacingBeforeAssignments knob
                11 => ['Squiz.WhiteSpace.OperatorSpacing.SpacingBefore'],
                // dangling "." outside a condition — OperatorLineBreak's to own
                13 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
                // dangling "||" inside the if — OneConditionPerLine only
                17 => ['CleanCode.Conditionals.OneConditionPerLine.BooleanOperatorNotLeading'],
                // "if ( ! " paren padding — PSR12 only; NotOperatorSpacing defers
                23 => ['PSR12.ControlStructures.ControlStructureSpacing.SpacingAfterOpenBrace'],
                // dangling "===" inside a multi-condition — OperatorLineBreak
                // owns it (OneConditionPerLine polices only the boolean "||")
                28 => ['CleanCode.Operators.OperatorLineBreak.OperatorAtLineEnd'],
            ],
            $this->violationSourceMap($file)
        );
    }

    /**
     * Flatten errors and warnings to line => sorted violation source codes.
     *
     * @return array<int, array<int, string>>
     */
    private function violationSourceMap(LocalFile $file): array
    {
        $map = [];

        foreach ([$file->getErrors(), $file->getWarnings()] as $violations) {
            foreach ($violations as $line => $columns) {
                foreach ($columns as $errors) {
                    foreach ($errors as $error) {
                        $map[$line][] = $error['source'];
                    }
                }
            }
        }

        ksort($map);
        array_walk($map, static function (array &$sources): void {
            sort($sources);
        });

        return $map;
    }
}

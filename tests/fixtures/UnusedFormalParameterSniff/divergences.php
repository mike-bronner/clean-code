<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter;

/**
 * The shapes where this sniff and PHPMD 2.15.0 disagree, plus the two where
 * they agree by both being blind.
 *
 * Nothing here is a shape the sniff gets wrong. Each is a decision recorded in
 * docs/phpmd/unusedcode-unusedformalparameter.md and pinned here so that it
 * cannot change without a test noticing. Every PHPMD verdict quoted below was
 * measured on a live 2.15.0 run over this exact file, not read off phpmd.org.
 */

// --- Stricter here: constructs PDepend never hands to a MethodAware rule ---

// PHPMD: silent. Its rule is FunctionAware and MethodAware, so a closure is
// invisible to it. Reported here — the same defect in a different construct.
$closureUnused = function (string $unusedA): void {
    echo 'x';
};

// PHPMD: silent, for the same reason.
$arrowUnused = fn (string $unusedB): string => 'x';

// PHPMD: silent. PDepend does not surface a method of an anonymous class.
// CleanCode.Functions.ExcessiveParameterList makes the same call on the same
// construct, so the two sniffs stay consistent with each other.
$anonymous = new class {
    public function method(string $unusedC): void
    {
        echo 'x';
    }
};

// --- The cost #120 accepted when it chose full parity ---

/**
 * Both tools report this one, and they agree *here* only because neither can
 * see the base: PHPMD is running over this file alone, so Vendor\Unknown\Base
 * is outside its type map too. That agreement is exactly what makes this the
 * boundary case rather than a divergence on paper.
 *
 * The two part company in a whole-project PHPMD run, where PDepend does have
 * the base and `MethodNode::isDeclaration()` finds handle() on it: PHPMD then
 * goes silent, and this sniff — which cannot resolve a parent declared in
 * another file at any time — still reports. That residual over-report is the
 * cost #120 accepted, and it cannot be pinned from a single file, so it is
 * recorded here and in the rule's doc instead of being asserted.
 *
 * Annotating the method with @inheritdoc or #[\Override] silences it, which is
 * the documented fix and is worth having on its own. passing.php pins that the
 * annotations do silence it.
 */
class ExtendsUnseenParent extends \Vendor\Unknown\Base
{
    public function handle(string $unusedD): void
    {
        echo 'x';
    }
}

// --- Same position, against the expectation that they differ ---

/**
 * Both tools report $unusedF, and both anchor it to the parameter's own line —
 * not to the line the declaration opens on, two lines above it. The exact
 * numbers are asserted in tests/Standards/UnusedFormalParameterTest.php rather
 * than quoted here, so that editing this file cannot leave a stale claim in a
 * comment nothing checks.
 *
 * This shape is here because the opposite was assumed while building the
 * sniff: PHPMD's message reads like a per-declaration report, and on every
 * single-line signature the two positions coincide, so nothing in the parity
 * set can tell them apart. Spanning the signature across lines separates them,
 * and the measurement says PHPMD anchors to the parameter. So reporting at the
 * parameter token — which #120's acceptance criteria ask for on column
 * precision grounds — costs no line-level agreement anywhere.
 */
function multiLineSignature(
    string $usedE,
    string $unusedF,
): void {
    echo $usedE;
}

// --- Blind in both tools ---

// A dynamic read. Neither tool resolves ${'unusedG'} to the parameter, so both
// report it as unused. Recorded rather than worked around: resolving it would
// mean evaluating the string expression.
function dynamicRead(string $unusedG): void
{
    echo ${'unusedG'};
}

// A nested declaration that reuses the name. Both tools count the inner $h as
// a read of the outer parameter and stay silent, because both collect variable
// mentions across the whole body rather than per nested scope.
function shadowedName(string $h): void
{
    $inner = function (string $h): string {
        return $h;
    };

    $inner('x');
}

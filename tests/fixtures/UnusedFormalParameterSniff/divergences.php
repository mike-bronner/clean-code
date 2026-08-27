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

// PHPMD: silent. A trait used inside a nested anonymous class is not the outer
// class's, and collide() below overrides nothing — but PDepend attributes the
// import to the enclosing class, so PHPMD reads the child as an override and
// says nothing. Reported here: the parameter really is dead.
trait NestedTrait
{
    public function collide(string $value): string
    {
        return $value;
    }
}

class NestsAnonymousClass
{
    public function build(): object
    {
        return new class {
            use NestedTrait;
        };
    }
}

class InheritsNestedAnonymousClass extends NestsAnonymousClass
{
    public function collide(string $unusedH): string
    {
        return 'x';
    }
}

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

// --- Stricter here: a first-class callable is not a call ---

// Spelled with the leading backslash on purpose. An unqualified call inside a
// namespace is already a divergence of its own — PHPMD takes it for a
// namespaced function and reports through it (passing.php's $eta) — so only
// the qualified spelling, which PHPMD does honour, isolates the decision this
// pair is here to pin.
//
// PHPMD: silent. `\func_get_args(...)` is PHP 8.1's first-class callable
// syntax, and PDepend reads it as the call it resembles, so PHPMD grants the
// whole-signature exemption a real \func_get_args() would earn. Nothing is
// called: the syntax builds a Closure, and that Closure cannot reach these
// parameters however it is later invoked — func_get_args() refuses to run
// outside the function whose arguments it reads, so calling it throws
// ("func_get_args() cannot be called from the global scope", PHP 8.4).
// Reported here, because the parameter really is dead and the exemption would
// rest on a call that never happens.
function firstClassFuncGetArgs(string $unusedH): callable
{
    return \func_get_args(...);
}

// The same syntax with the other exempting call, and the same qualified
// spelling for the same reason. compact(...) names no parameter either way,
// so PHPMD reports this one too — but through its argument list rather than
// through the syntax, which is the second door into the same misreading, and
// it is pinned so that closing one does not leave the other open.
function firstClassCompact(string $unusedI): callable
{
    return \compact(...);
}

// A genuine spread is still a call, and still exempts. `...[]` puts an array
// where the first-class callable syntax puts the closer, which is the whole of
// the difference between them — and it spreads to zero arguments, so this is
// exactly the call `\func_get_args()` would be, and it runs. $j is read
// through nothing else, so widening the first-class-callable test to any
// leading ellipsis reports it. Silent in both tools.
function spreadFuncGetArgs(string $j): array
{
    return \func_get_args(...[]);
}

// --- Stricter here: names that only spell an exempting call ---

// PHPMD: silent. It matches `compact` by suffix, so the last segment of a
// qualified name satisfies it and the parameter the argument list names is
// exempted. The call reaches `Vendor\Package\compact()` — somebody else's
// function, which has no obligation to bring `$unusedK` into scope — so the
// parameter really is dead and is reported here. Resolved by
// CleanCode\Helpers\FunctionCalls, which every sniff asking this question
// shares (#320); the hand-rolled copy this sniff used to carry stepped over
// the qualifier and matched the suffix exactly as PHPMD does.
function qualifiedCompact(string $unusedK): array
{
    return \Vendor\Package\compact('unusedK');
}

// PHPMD: silent, for the same reason read the other way round — the name is
// spelled bare, so the suffix match succeeds without the import being read at
// all. `use function` below redirects the bare name to the very function
// above, so this call is that one and not PHP's, and $unusedL is dead.
//
// An import binds for the whole namespace block wherever in it the statement is
// written, so this one is in force above its own line as well. That is safe
// only because no other shape in this file spells `compact` bare with an
// argument list — `\compact(...)` on line 170 is qualified and names no
// parameter. Adding one above this point would be redirected by this import,
// so it belongs in a file of its own rather than here.
use function Vendor\Package\compact;

function importedCompact(string $unusedL): array
{
    return compact('unusedL');
}

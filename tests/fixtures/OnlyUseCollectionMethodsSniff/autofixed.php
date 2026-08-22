<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Collection as Coll;

function genericFunctionsOnAssignedCollections(array $rows): array
{
    $collection = collect($rows);

    return [
        array_map(static fn (array $row): string => $row['name'], $collection),
        array_filter($collection),
        array_reduce($collection, static fn (int $carry): int => $carry, 0),
        array_keys($collection),
        array_values($collection),
        $collection->count(),
        in_array('a', $collection, true),
        implode(', ', $collection),
    ];
}

function genericFunctionsOnConstructedCollections(array $rows): array
{
    $made = Collection::make($rows);
    $wrapped = Collection::wrap($rows);
    $constructed = new Collection($rows);

    return [
        $made->sum(),
        array_slice($wrapped, 0, 1),
        array_unique($constructed),
    ];
}

function genericFunctionsOnChainedCollections(Collection $collection, array $rows): array
{
    return [
        $collection->filter(static fn (string $row): bool => $row !== '')->count(),
        collect($rows)->map(static fn (string $row): string => $row)->count(),
        array_merge($collection->values(), $rows),
    ];
}

function genericFunctionsOnQualifiedAndNullsafeCollections(Collection $collection): array
{
    return [
        $collection->count(),
        array_values($collection?->filter(static fn (string $row): bool => $row !== '')),
    ];
}

// count()'s $mode argument changes the result, so the multi-argument form is
// reported but never auto-fixed.
function multiArgumentCallsAreReportedButNotFixable(Collection $collection): int
{
    return count($collection, COUNT_RECURSIVE);
}

// The remaining mapped functions, so every entry in the mapping table is
// pinned — the argument-order-reversed ones (array_key_exists => has,
// array_search => search) most of all.
function remainingMappedFunctionsAreFlagged(Collection $collection, array $rows): array
{
    return [
        array_diff($collection, $rows),
        array_intersect($collection, $rows),
        array_key_exists('a', $collection),
        array_search('a', $collection, true),
        join(', ', $collection),
    ];
}

// An aliased import is judged by the class it names, not by the alias.
function aliasedCollectionImportsAreFlagged(Coll $collection, array $rows): array
{
    $made = Coll::make($rows);

    return [
        $collection->count(),
        $made->count(),
    ];
}

// Shadowing cuts both ways: inside the arrow function its own Collection
// parameter *is* a Collection, and a name it does not declare still resolves to
// the enclosing scope's Collection by capture.
function arrowFunctionParametersBindInsideTheArrowFunction(Collection $outer): array
{
    return [
        static fn (Collection $rows): int => $rows->count(),
        static fn (): int => $outer->count(),
    ];
}

// A chain whose every link is on CHAINABLE_METHODS is a Collection for certain,
// so the fixer rewrites it: filter() and map() both state a Collection return on
// Illuminate's Enumerable, and the origins they hang off are proven outright.
function provableChainedReceiversAreFixable(Collection $collection, array $rows): array
{
    return [
        $collection->filter(static fn (string $row): bool => $row !== '')->count(),
        collect($rows)->map(static fn (string $row): string => $row)->count(),
    ];
}

// PHP lets any callee rebind a caller's variable through a `&$parameter`, and
// neither a userland signature nor the by-reference builtins are knowable from
// this file's tokens. Mirroring PHP's by-reference builtins would be another
// moving third-party API whose every omission is a false positive, so a bare
// variable handed to any other call is instead treated as no longer provably
// unmutated: still reported, never rewritten. The severity collapses rather
// than relocating.
function byReferenceArgumentsAreReportedButNeverFixable(Collection $collection, string $subject): array
{
    receivesByReference($collection);
    preg_match('/[a-z]+/', $subject, $collection);

    return [
        count($collection),
        array_sum($collection),
    ];
}

function receivesByReference(mixed &$target): void
{
    $target = [1, 2, 3];
}

// Retirement has to be aimed at the names a construct actually binds. The three
// below sit next to a construct that retires *something*, and must survive it:
// each is still a Collection at the call, so each stays reported and fixable.
// Retiring too broadly would silence them, which no error count would reveal.

// A foreach binds the names after `as`, and reads the subject. Iterating a
// Collection must not retire the Collection.
function foreachSubjectKeepsItsTracking(Collection $data): int
{
    $total = 0;

    foreach ($data as $row) {
        $total += (int) $row;
    }

    return $total + $data->count();
}

// A by-value capture binds a copy inside the closure and leaves the outer name
// alone; only `&` rebinds it.
function valueCaptureLeavesTheOuterNameAlone(Collection $data): int
{
    $read = static function () use ($data): int {
        return $data->count();
    };

    return $read() + $data->count();
}

// A declaration's parameter list is not a call, so an untyped parameter later
// assigned a Collection is not treated as having escaped by reference.
function untypedParameterAssignedACollectionStaysFixable(array $rows, $data): int
{
    $data = collect($rows);

    return $data->count();
}

// A declaration's parameter list is not a call. Treated as one, the parameter
// name below would register as having escaped by reference — and because a
// parameter token resolves to the *file* scope rather than the function's, it
// would land in the same bucket as the file-scope Collection of that name and
// quietly cost it its autofix.
function declaresAParameterSharingAFileScopeName($tally): void
{
    unset($tally);
}

$tally = collect([1, 2]);
$fileScopeTotal = $tally->count();

// The fixer re-derives a chained receiver's type from CHAINABLE_METHODS, which
// fails closed: a link it does not recognise ends provability. Both calls below
// are still reported — TERMINAL_METHODS has never heard of either method, so to
// the report the chain is a Collection still — and neither may be rewritten.
// chunk() hands back a Collection of Collections, and a method no list knows
// could hand back anything at all.
function unrecognisedChainLinksAreReportedButNeverFixable(Collection $collection): int
{
    return count($collection->chunk(2))
        + count($collection->someMethodNotOnTheList());
}

// Provability runs the whole length of the chain, not just its last link: every
// method here is on the list, so the receiver stays proven across all three.
function everyLinkOfALongChainIsChecked(Collection $collection): int
{
    return $collection->filter(static fn (string $row): bool => $row !== '')->values()->unique()->count();
}

// A nullsafe link can yield null, so no method after it is reached on a
// Collection for certain. Reported, never rewritten.
function nullsafeChainsAreReportedButNeverFixable(?Collection $collection): int
{
    return count($collection?->filter(static fn (string $row): bool => $row !== ''));
}

// An untyped static property is spelled exactly like a function-local
// `static $ledger;`, and scopeOf() resolves a class body to the file scope — the
// same bucket a file-scope Collection sits in. Reading the declaration as a
// rebinding retires that Collection and takes a real violation down with it,
// which is the worst direction available to a linter: the file looks clean.
class UntypedStaticPropertiesDeclareNoLocal
{
    public static $ledger = [];

    private static $register;
}

$ledger = collect([1, 2]);
$register = collect([3, 4]);
$ledgerTotal = $ledger->count();
$registerTotal = $register->count();

// A union or intersection hint naming a Collection makes the parameter one: the
// hint is split on both separators before each part is tested. A check that read
// the compound string whole would stop tracking both parameters below, and
// neither call would be reported at all.
function unionHintedParametersAreCollections(Collection|ArrayObject $union): int
{
    return count($union);
}

function intersectionHintedParametersAreCollections(Collection&Countable $intersection): int
{
    return $intersection->count();
}

// The parameter-list exemption above, behind a reference marker. Returning by
// reference puts an `&` between the keyword and the name, hiding the keyword
// from the check that tells a declaration's parameter list from a call's
// arguments — so this parameter registered as escaped by reference, in the same
// file-scope bucket as the Collection of that name, and quietly cost line 222
// its autofix. Nothing here is reported; the pin is that line 222 stays [x].
function &declaresAByReferenceParameterSharingAFileScopeName($ledger)
{
    return $ledger;
}

// Below: reported, never rewritten. Each one is a value the hint or the
// expression says *may* be a Collection, which is the whole of what reporting
// asks and none of what the fixer needs. Line 231's union is the same shape.

// A union is a choice, so a member that is not a Collection is a value the
// receiver can hold. `count($mixed)` is right for an array and the rewrite
// fatals on one, so the hint reports and proves nothing.
function unionWithANonCollectionMemberIsNotProvable(Collection|array $mixed): int
{
    return count($mixed);
}

// Null is the same choice written shorter. `?Collection` reports, because a
// Collection is one of the things it holds, and proves nothing.
function nullableCollectionsAreNotProvable(?Collection $maybe): int
{
    return count($maybe);
}

// A variable is not a laundering step. The assignment is read by the fail-open
// walk, which assumes an unknown method left a Collection standing — right for
// reporting, no proof at all for the fixer. count($rows) below and the same
// call written inline are the identical expression, so they get the identical
// verdict: reported, unfixable. Before the strength was recorded alongside the
// name, going through the variable turned the assumption into a rewrite.
function anUnknownMethodDoesNotBecomeProvableThroughAVariable(Collection $ledger): int
{
    $rows = $ledger->toRowsArray();

    return count($rows);
}

// The inline half of the pair above, which was already declining the fix. It
// is here so the two verdicts sit in one file: if a change ever lets the
// variable launder again, only one of these two lines moves.
function theSameUnknownMethodInline(Collection $ledger): int
{
    return count($ledger->toRowsArray());
}

// An arrow function's parameters are mapped apart from every other
// declaration's, so the reported-versus-proven split has to be answered there
// too. A union-hinted arrow parameter is the shape that tells the two answers
// apart: reported like line 259, and unfixable for the same reason. Read under
// the reporting polarity alone, this line would be rewritten.
function unionHintedArrowParametersAreNotProvable(): callable
{
    return fn (Collection|array $mixed): int => count($mixed);
}

// A call made through a callable *expression* has no name for the sniff to look
// up, so nothing proves its parameters are by value — and a parameter declared
// `&$x` rebinds the caller's variable. Each shape below is one member of
// CALLABLE_EXPRESSION_ENDERS, and each must be reported (the generic function is
// still the wrong call) and never rewritten (the receiver may no longer be a
// Collection by then). Read as "matched no known callee shape, so it must be
// safe", every one of these is rewritten into a runtime fatal.
//
// An immediately-invoked closure: the token before the argument list is the `)`
// closing the closure itself.
function anImmediatelyInvokedClosureMayRebindItsArgument(Collection $data): int
{
    (function (&$x): void {
        $x = 'not a collection anymore';
    })($data);

    return count($data);
}

// A closure reached through an array index: the token before the argument list
// is the `]` closing the subscript.
function anIndexedCallableMayRebindItsArgument(Collection $data, array $callbacks): int
{
    $callbacks['key']($data);

    return count($data);
}

// A closure returned by a method call, then invoked: the `)` here closes the
// *producing* call rather than a closure literal, so a check that recognised
// only the literal form still misses it.
function aReturnedClosureMayRebindItsArgument(Collection $data, object $factory): int
{
    ($factory->getMutator())($data);

    return count($data);
}

// A method named at runtime: the token before the argument list is the `}`
// closing the dynamic name.
function aDynamicMethodNameMayRebindItsArgument(Collection $data, object $handler, string $name): int
{
    $handler->{$name}($data);

    return count($data);
}

// A constructor is a call like any other and may declare `&$items`. An
// anonymous class has no name token at all, so it is the shape that proves the
// admission set is read rather than the callee resolved.
function anAnonymousClassConstructorMayRebindItsArgument(Collection $data): int
{
    new class ($data) {
        public function __construct(mixed &$items)
        {
            $items = 'not a collection anymore';
        }
    };

    return count($data);
}

// `self` and `static` name the class being instantiated in place of a T_STRING,
// so each needs its own entry and its own line here.
class LateStaticConstructorsMayRebindTheirArguments
{
    public function viaSelf(Collection $data): int
    {
        new self($data);

        return count($data);
    }

    public function viaStatic(Collection $data): int
    {
        new static($data);

        return count($data);
    }
}

<?php

declare(strict_types=1);

/**
 * Variant-shape fixture for CleanCode.Constructors.DisallowCombinedConstructor.
 *
 * failing.php proves each signal fires at all. This file is the guard against a
 * token walk that only ever handled the one spelling it was written against —
 * every branching form the signals can sit in, every declaration form a
 * constructor can take, the argument readers in the places a body can put them,
 * every way a parameter can qualify as a mode flag, the predicates that take a
 * second argument, and a comment wherever the walk needs two adjacent tokens.
 */

final class Mailer
{
}

final class NullLogger
{
}

/**
 * Every branching form a mode flag can head: `if`, `elseif`, a spaced
 * `else if`, `switch`, `match` (as the subject and as an arm condition), a
 * ternary, a brace-less `if`, and the alternative syntax.
 */
final class ModeFlagShapes
{
    public function __construct(bool $queued, bool $eager, bool $lazy)
    {
        if ($queued) {
            $this->transport = new Mailer();
        } elseif ($eager) {
            $this->transport = new NullLogger();
        } else if ($lazy) {
            $this->transport = new Mailer();
        }

        switch ($queued) {
            case true:
                $this->mode = 'queued';
                break;
            default:
                $this->mode = 'direct';
        }

        $this->kind = match ($eager) {
            true => new Mailer(),
            false => new NullLogger(),
        };

        $this->flavour = match (true) {
            $lazy => new NullLogger(),
            default => new Mailer(),
        };

        $this->pick = $queued ? new Mailer() : new NullLogger();

        if ($eager) $this->warm = new Mailer();

        if ($lazy):
            $this->cold = new NullLogger();
        endif;
    }
}

/**
 * Every form of type test: `instanceof`, each predicate spelling, and a
 * predicate reached through a ternary and a `match` arm rather than an `if`.
 */
final class TypeSwitchShapes
{
    public function __construct(mixed $source, object $handler, mixed $extra)
    {
        if ($handler instanceof Mailer) {
            $this->handler = $handler;
        } else {
            $this->handler = new NullLogger();
        }

        if (is_array($source)) {
            $this->lines = $source;
        } elseif (is_callable($source)) {
            $this->lines = [$source];
        }

        if (gettype($extra) === 'integer') {
            $this->extra = [$extra];
        } else {
            $this->extra = [];
        }

        $this->tag = is_string($source) ? 'text' : 'other';

        $this->shape = match (true) {
            is_object($extra) => new Mailer(),
            default => new NullLogger(),
        };

        switch (true) {
            case is_iterable($extra):
                $this->extra = [...$extra];
                break;
            default:
                $this->extra = [];
        }
    }
}

/**
 * The argument readers do not need a branch to be a mode signal, so they are
 * reported wherever they appear in the body — including inside a nested
 * conditional and inside a `foreach`.
 */
final class ArgumentCountShapes
{
    public function __construct()
    {
        if (func_num_args() > 1) {
            $this->arguments = func_get_args();
        }

        foreach (FUNC_GET_ARGS() as $argument) {
            $this->tail[] = $argument;
        }
    }
}

/**
 * Constructors that are not a plain class's: a trait's, an anonymous class's,
 * and one spelled `__CONSTRUCT`, since PHP method names are case-insensitive.
 * The promoted parameter proves promotion does not hide a flag from the body
 * scan.
 */
trait BuildsItself
{
    public function __construct(private bool $queued)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();
    }
}

final class ShoutsItsName
{
    public function __CONSTRUCT(bool $queued)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();
    }
}

final class HoldsAnonymousClass
{
    public function build(): object
    {
        return new class (true) {
            public function __construct(bool $queued)
            {
                $this->transport = $queued ? new Mailer() : new NullLogger();
            }
        };
    }
}

/**
 * A flag declared without the `bool` type, carrying a `true`/`false` default
 * instead. This is the second leg of the flag test — an untyped parameter and a
 * `mixed`-typed one reach it and nothing else does, so a `bool`-typed parameter
 * cannot stand in for either.
 */
final class DefaultedModeFlags
{
    public function __construct($queued = false, mixed $eager = true)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();

        if ($eager) {
            $this->warm = new Mailer();
        }
    }
}

/**
 * The two predicates that take more than one argument. Only the first argument
 * is the subject being type-tested, so `$source` is a signal and the
 * `$expectedClass` it is compared against — a value the call reads, never a
 * parameter whose own type is switched on — is not.
 */
final class MultiArgumentPredicates
{
    public function __construct(mixed $source, string $expectedClass)
    {
        if (is_a($source, $expectedClass)) {
            $this->handler = $source;
        } else {
            $this->handler = new NullLogger();
        }

        $this->child = is_subclass_of($source, $expectedClass) ? new Mailer() : new NullLogger();
    }
}

/**
 * A comment standing between two tokens the walk needs adjacent. Every
 * adjacency test skips comments as well as whitespace, so each signal still
 * fires: a comment before an `instanceof`, before a predicate's call
 * parentheses, and before an argument reader's parentheses. A test that skipped
 * whitespace alone would miss all three. passing.php carries the same class of
 * case in the other direction, where a comment must not *create* a report.
 */
final class CommentedSignals
{
    public function __construct(object $handler, mixed $source)
    {
        if ($handler /* still a type test */ instanceof Mailer) {
            $this->handler = $handler;
        } else {
            $this->handler = new NullLogger();
        }

        if (is_string /* still a predicate call */ ($source)) {
            $this->source = $source;
        } else {
            $this->source = '';
        }

        if (func_num_args /* still the argument reader */ () > 1) {
            $this->extra = true;
        }
    }
}

/**
 * The nullable and explicit-union spellings of a `bool` type hint. Both
 * normalize to plain `bool` — the leading `?` is stripped, an explicit `null`
 * member is dropped — so both are flags, and neither carries a `true`/`false`
 * default that could qualify it by the other leg instead. A union wider than
 * that is not a flag, and passing.php pins `bool|string`.
 */
final class NullableModeFlags
{
    public function __construct(?bool $queued, bool|null $eager)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();

        if ($eager) {
            $this->warm = new Mailer();
        }
    }
}

/**
 * A signal separated from its selector by a comma — once inside another call's
 * argument list, once inside an array literal. A comma separates the elements
 * of the group it sits in rather than ending the expression: the flag's value
 * flows on as the group's own result, and the ternary that result selects on is
 * still the flag's branch.
 */
final class NestedGroupShapes
{
    public function __construct(bool $queued, mixed $value)
    {
        $this->transport = in_array($queued, [$value], true) ? new Mailer() : new NullLogger();

        $this->fallback = [$queued, $value][0] ? new Mailer() : new NullLogger();
    }
}

/**
 * A `match` standing as an operand of the expression a ternary selects on. Its
 * arm list is a braced group in the middle of that expression, so the scan
 * jumps the whole construct — subject parentheses, arm list and all — instead
 * of reading the arm list's opening brace as the end of the expression.
 */
final class MatchOperandShapes
{
    public function __construct(bool $queued, mixed $value)
    {
        $this->transport = $queued && match ($value) { 'draft' => true, default => false }
            ? new Mailer() : new NullLogger();
    }
}

/**
 * A flag whose expression carries a parenthesised group holding a selector of
 * its own — a guard clause's ternary, passed as an argument. The group is
 * jumped whole, so the branch the flag heads is the outer ternary and not the
 * throwing one inside the call: a scan that read the inner selector as the
 * flag's own would take the flag for a guard and stay silent.
 */
final class ParenthesisedOperandShapes
{
    public function __construct(bool $queued, string $mode)
    {
        $this->transport = $queued && $this->pick($mode === 'draft' ? throw new InvalidArgumentException('no') : 'b')
            ? new Mailer()
            : new NullLogger();
    }
}

/**
 * A flag and a type test whose selector sits behind a comma-separated sibling
 * the tokenizer ends *with the group's own closing token*: an arrow function
 * standing last in a call's argument list, then last in an array literal. The
 * comma is resolved from the group holding it rather than by scanning for a
 * closer, so the sibling cannot swallow the group's end and hide the selector.
 */
final class TrailingArrowFunctionSiblings
{
    public function __construct(bool $legacy, mixed $source, array $items)
    {
        $this->transport = $this->pick($legacy, fn ($item) => $item + 1)
            ? new Mailer()
            : new NullLogger();
        $this->logger = [$legacy, fn () => $items][0]
            ? new Mailer()
            : new NullLogger($source);
    }
}

/**
 * A `match` arm listing several conditions, with the signal in front of the
 * comma rather than last. The arm's condition list is bounded by the arm's own
 * `=>` and by nothing else, so the comma carries the scan on to that arrow
 * instead of ending the expression.
 */
final class MultiConditionArms
{
    public function __construct(bool $legacy, mixed $source, float $size)
    {
        $this->transport = match (true) {
            $legacy, $size > 0.0 => new Mailer(),
            default => new NullLogger(),
        };
        $this->logger = match (true) {
            is_string($source), $size > 0.0 => new Mailer(),
            default => new NullLogger(),
        };
    }
}

/**
 * The global functions written in their fully-qualified form. A leading `\`
 * with no namespace in front of it names the global function itself, so both
 * signals report exactly as the bare spelling does.
 */
final class RootQualifiedCalls
{
    public function __construct(mixed $source)
    {
        $this->kind = \is_string($source) ? 'text' : 'other';
        $this->count = \func_num_args();
    }
}

/**
 * A `switch` with no case at all. Nothing in it throws, so the flag is not
 * guarding a precondition — a guard needs a branch that rejects the call.
 */
final class EmptyBranchConstruct
{
    public function __construct(bool $legacy)
    {
        switch ($legacy) {
        }
    }
}

/**
 * Two surviving construction paths and a rejecting third. One throwing branch
 * does not make the condition a guard while more than one way of constructing
 * is left, whichever construct spells the branches.
 */
final class SurvivingPathsBesideAThrow
{
    public function __construct(bool $legacy, mixed $source)
    {
        if ($legacy) {
            $this->transport = new Mailer();
        } elseif ($source instanceof Mailer) {
            $this->transport = $source;
        } else {
            throw new InvalidArgumentException('unsupported construction');
        }
    }
}

/**
 * A `match` whose flag arm rejects while two arms still construct. The flag's
 * own branch throws, so the flag is a guard however many paths survive beside
 * it; the type test that picks between those two paths is the mode switch, and
 * reports.
 */
final class RejectingArmBesideSurvivors
{
    public function __construct(bool $legacy, mixed $source)
    {
        $this->transport = match (true) {
            $legacy => throw new InvalidArgumentException('legacy construction is not supported'),
            is_string($source) => new Mailer(),
            default => new NullLogger(),
        };
    }
}

/**
 * A predicate whose subject is wrapped in comments on both sides. The subject
 * is still the bare first argument — a comment is not decoration the call
 * reads — so the type test reports.
 */
final class CommentedPredicateArguments
{
    public function __construct(mixed $source)
    {
        $this->kind = is_string(/* the subject */ $source /* still the subject */)
            ? 'text'
            : 'other';
    }
}

/**
 * A mode flag branching in the arguments of an anonymous class. Those
 * arguments are evaluated by this constructor, wherever the class they
 * construct is declared, so the branch is this constructor's own.
 */
final class AnonymousClassArguments
{
    public function __construct(bool $legacy)
    {
        $this->handler = new class ($legacy ? new Mailer() : new NullLogger()) {
            public function __construct(public mixed $transport)
            {
            }
        };
    }
}

/**
 * A type predicate whose subject is wrapped in a redundant grouping
 * parenthesis. The parentheses hold the parameter and nothing else, so the
 * call tests the parameter itself, exactly as the unwrapped spelling does.
 */
final class GroupedPredicateSubject
{
    public function __construct(mixed $source)
    {
        if (is_string(($source))) {
            $this->lines = explode("\n", $source);
        } else {
            $this->lines = [$source];
        }
    }
}

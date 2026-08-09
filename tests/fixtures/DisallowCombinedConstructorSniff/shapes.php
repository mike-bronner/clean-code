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
 * second argument, a comment wherever the walk needs two adjacent tokens, and
 * every way a group can stand between a signal and the selector it feeds.
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

<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Constructors.DisallowCombinedConstructor.
 *
 * Two things keep this file discriminating:
 *
 *   1. The compliant form of the construct the sniff registers on — a primary
 *      constructor that initializes exactly one way, with the construction
 *      scenarios expressed as named constructors that delegate to it.
 *   2. Every near-miss shape the sniff must stay silent on: guard clauses in
 *      each branching form (braced, brace-less, alternative syntax, ternary,
 *      `match`, `switch`) and for each of the three signals — the argument
 *      readers included — coalesce defaults (`??` and the elvis `?:`) over a
 *      mode flag, a non-boolean parameter in a condition, a `bool|string`
 *      union too wide to be a flag, a type predicate applied to a *derived*
 *      value rather than the parameter — through a nested call, a property
 *      read, a subscript, and a named argument — all three signals inside a
 *      named constructor and inside an ordinary method, all three inside a
 *      named function, a closure and an arrow function declared in a
 *      constructor body, a `__constructor()` lookalike, a plain
 *      `function __construct()` at file scope, member and static calls that
 *      merely share a name with a predicate or with the argument readers —
 *      including with a comment splitting the object operator from the name —
 *      every bodiless constructor shape, the far side of every expression
 *      boundary the forward scan must respect, and guards holding a nested
 *      construct of the same kind as their own.
 *
 * Dropping any one of the sniff's guards reddens this file.
 */

final class Mailer
{
}

final class NullLogger
{
}

/**
 * The standard as it should be written: one unconditional primary constructor,
 * one named constructor per construction scenario, each delegating to it.
 */
final class Report
{
    public function __construct(
        private string $title,
        private bool $draft,
        private ?object $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public static function draft(string $title): self
    {
        return new self($title, true);
    }

    public static function published(string $title): self
    {
        return new self($title, false);
    }
}

/**
 * Guard clauses. Each branch's first statement is a `throw`, so each condition
 * is validating a precondition rather than selecting an initialization path —
 * and that holds for all three signals, in every branching form.
 */
final class Guarded
{
    public function __construct(bool $strict, mixed $value, object $handler)
    {
        if ($strict) {
            throw new LogicException('strict construction is not supported');
        }

        if (is_string($value)) {
            throw new InvalidArgumentException('a string source needs Guarded::fromString()');
        }

        if ($handler instanceof Mailer) {
            throw new InvalidArgumentException('mailers are not handlers');
        }

        $this->value = $value;
    }
}

final class GuardedBraceless
{
    public function __construct(bool $eager, mixed $value)
    {
        if ($eager) throw new LogicException('eager construction is not supported');

        if (is_array($value)) throw new InvalidArgumentException('use fromArray()');

        $this->value = $value;
    }
}

final class GuardedAlternativeSyntax
{
    public function __construct(bool $lazy, mixed $value)
    {
        if ($lazy):
            throw new LogicException('lazy construction is not supported');
        endif;

        if (is_int($value)):
            throw new InvalidArgumentException('use fromId()');
        endif;

        $this->value = $value;
    }
}

final class GuardedExpressions
{
    public function __construct(bool $legacy, mixed $value)
    {
        $this->checked = $legacy ? throw new LogicException('legacy') : $value;
        $this->typed = is_callable($value) ? throw new InvalidArgumentException('callable') : $value;

        $this->mode = match ($legacy) {
            true => throw new LogicException('legacy'),
            default => throw new LogicException('unsupported'),
        };

        $this->arm = match (true) {
            $legacy => throw new LogicException('legacy'),
            is_object($value) => throw new InvalidArgumentException('object'),
            default => throw new InvalidArgumentException('unsupported'),
        };

        switch ($legacy) {
            case true:
                throw new LogicException('legacy');
            default:
                throw new LogicException('unsupported');
        }
    }
}

/**
 * A guard clause carrying an `else` branch. The standard's constraint is stated
 * as "a branch whose body only throws is never flagged", so the condition of a
 * throwing branch stays silent even when the statement continues — the `else`
 * is the single surviving initialization path, not a second one.
 */
final class GuardedWithElse
{
    public function __construct(bool $legacy, string $name)
    {
        if ($legacy) {
            throw new LogicException('legacy construction was removed');
        } else {
            $this->name = $name;
        }
    }
}

/**
 * The argument readers inside guard clauses. Reading the argument list to
 * *reject* a call is validating a precondition, not overloading the
 * constructor, so the exemption covers this signal exactly as it covers the
 * other two — in a braced `if`, a brace-less one, and a ternary that throws.
 */
final class GuardedArgumentCount
{
    public function __construct(mixed $value = null)
    {
        if (func_num_args() > 1) {
            throw new InvalidArgumentException('GuardedArgumentCount takes one argument');
        }

        if (func_get_args() === []) throw new InvalidArgumentException('GuardedArgumentCount needs a value');

        $this->checked = func_num_args() === 0 ? throw new LogicException('no arguments') : $value;
    }
}

/**
 * Coalesce defaults. `??` carries no branching token at all, and `?:` supplies
 * a default for one expression rather than selecting between two — both are
 * benign, and both are applied to a mode flag here so the exclusion is
 * genuinely exercised.
 */
final class Defaulted
{
    public function __construct(bool $verbose, ?object $logger = null, mixed $value = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->verbose = $verbose ?: false;
        $this->value = $value ?? new Mailer();
    }
}

/**
 * Conditions that are not mode signals: a non-boolean parameter compared to a
 * value, and a type predicate applied to a value *derived* from a parameter
 * rather than to the parameter itself.
 */
final class NotAModeSignal
{
    public function __construct(int $retries, string $source, bool $flag)
    {
        if ($retries > 3) {
            $this->retries = 3;
        } else {
            $this->retries = $retries;
        }

        if (is_numeric(trim($source))) {
            $this->source = (int) $source;
        } else {
            $this->source = 0;
        }

        $this->flag = $flag;
    }
}

/**
 * A mode flag whose expression ends before any selector is reached. Every
 * statement here puts a ternary *after* the flag's own expression, separated by
 * exactly one of the boundaries the forward scan has to respect — a `;`, a `,`,
 * an array `=>`, a subscript it must step over whole, the `{` of a loop body,
 * and the `:` of an alternative-syntax block. A scan that ran past any one of
 * them would attribute that ternary to the flag, so each boundary is exercised
 * on its own.
 */
final class ExpressionEnds
{
    public function __construct(bool $verbose, int $retries, iterable $rows)
    {
        $this->verbose = $verbose;
        $this->retries = $retries > 3 ? 3 : $retries;

        $this->transport = $this->make($verbose, $retries > 3 ? new Mailer() : new NullLogger());

        $this->pairs = [$verbose => $retries > 3 ? 'many' : 'few'];

        $this->label = $this->labels[$verbose][$retries > 3 ? 'capped' : 'open'];

        while ($verbose === false) {
            break;
        }

        $this->state = $retries > 3 ? 'capped' : 'open';

        foreach ($this->pick($verbose) as $row):
            $this->rows[] = $retries > 3 ? 'capped' : 'open';
        endforeach;
    }
}

/**
 * A `case` label that is not a mode signal, followed by a ternary in the case
 * body. The label's colon ends the label's expression, so the ternary belongs
 * to the body rather than to the label — while a label that *is* a signal
 * reports against its `switch`, as shapes.php pins.
 */
final class CaseLabels
{
    public function __construct(string $mode, int $retries)
    {
        switch ($mode) {
            case 'draft':
                $this->transport = $retries > 3 ? new Mailer() : new NullLogger();
                break;
            default:
                $this->transport = new NullLogger();
        }
    }
}

/**
 * A variadic parameter is a list of values, never a single mode signal — and
 * PHP forbids a default on one, so it cannot be a flag by default either.
 */
final class Variadic
{
    public function __construct(bool ...$flags)
    {
        $this->first = $flags[0] ?? false;
        $this->any = $flags !== [] ? 'some' : 'none';
    }
}

/**
 * Everything the sniff refuses to inspect: a named constructor, an ordinary
 * method, and a `__constructor()` lookalike — each carrying all three signals.
 */
final class OtherMethods
{
    public function __construct(private string $name)
    {
    }

    public static function fromMixed(bool $draft, mixed $value): self
    {
        $count = func_num_args();
        $arguments = func_get_args();

        if ($draft) {
            return new self('draft');
        }

        return is_string($value) ? new self($value) : new self('unknown');
    }

    public function reconfigure(bool $draft, mixed $value): void
    {
        $this->name = $draft ? 'draft' : 'published';
        $this->kind = is_array($value) ? 'list' : 'scalar';
        $this->count = func_num_args();
    }

    public function __constructor(bool $draft, mixed $value)
    {
        $this->name = $draft ? 'draft' : 'published';
        $this->kind = is_string($value) ? 'text' : 'other';
        $this->count = func_num_args();
    }
}

/**
 * Nested declarations run on their own terms: a closure's `func_get_args()`
 * reads the closure's arguments, not the constructor's, and an arrow function's
 * body executes when it is called rather than during construction. A named
 * function declared in the body is the fourth such declaration, and is skipped
 * on the same grounds as the other three.
 */
final class NestsDeclarations
{
    public function __construct(bool $lazy, mixed $value)
    {
        function makeTransport(bool $lazy, mixed $value): object
        {
            $arguments = func_get_args();

            if ($lazy) {
                return new Mailer();
            }

            return is_string($value) ? new Mailer() : new NullLogger();
        }

        $this->build = static function (bool $lazy, mixed $value) {
            $arguments = func_get_args();

            if ($lazy) {
                return new Mailer();
            }

            return is_string($value) ? new Mailer() : new NullLogger();
        };

        $this->pick = static fn (bool $lazy): object => $lazy ? new Mailer() : new NullLogger();

        $this->registry = new class {
            public function make(bool $lazy, mixed $value): object
            {
                $arguments = func_get_args();

                return $lazy || is_string($value) ? new Mailer() : new NullLogger();
            }
        };
    }
}

/**
 * Names that merely resemble the argument-list readers: a method call, a static
 * call, and a plain string.
 */
final class Lookalikes
{
    public function __construct(object $request)
    {
        $this->count = $request->func_num_args();
        $this->arguments = Reflector::func_get_args();
        $this->label = 'func_num_args';
    }
}

/**
 * Bodiless constructors: an abstract declaration, an interface declaration, and
 * a promotion-only body with no statements in it.
 */
abstract class AbstractSource
{
    abstract public function __construct(bool $draft, mixed $value);
}

interface SourceContract
{
    public function __construct(bool $draft, mixed $value);
}

final class PromotedOnly
{
    public function __construct(private bool $draft, private mixed $value)
    {
    }
}

/**
 * A plain function named `__construct` at file scope constructs nothing, so it
 * is not a constructor however it branches.
 */
function __construct(bool $draft, mixed $value)
{
    $count = func_num_args();

    return $draft || is_string($value) ? new Mailer() : new NullLogger();
}

/**
 * A comment standing where the walk needs adjacency, in the direction where a
 * comment must not *create* a report: a method that merely shares a name with a
 * predicate, a method that shares a name with an argument reader, and an elvis
 * default whose `?` and `:` are separated. An adjacency test that skipped
 * whitespace alone would read past the comment and report all three — shapes.php
 * carries the same class of case in the direction where the signal is real.
 */
final class CommentedLookalikes
{
    public function __construct(object $request, mixed $value, bool $verbose)
    {
        $this->handler = $this-> /* a method, not the predicate */ is_a($value, self::class)
            ? new Mailer()
            : new NullLogger();

        $this->count = $request-> /* a method, not the reader */ func_num_args();

        $this->verbose = $verbose ? /* still an elvis default */ : false;
    }

    private function is_a(mixed $value, string $class): bool
    {
        return $value instanceof $class;
    }
}

/**
 * A type predicate whose first argument is not the bare parameter. Each spelling
 * puts a parameter inside the first argument's span without that parameter being
 * what the call tests: a property read and a subscript both test a *derived*
 * value, the subscript's key is not tested at all, and a named-argument call
 * addresses its subject by name rather than by position. Confirming only that no
 * comma precedes the parameter would report every one of them.
 */
final class DecoratedPredicateArguments
{
    public function __construct(mixed $holder, mixed $key, array $items, mixed $source, string $expectedClass)
    {
        if (is_string($holder->prop)) {
            $this->prop = $holder->prop;
        } else {
            $this->prop = '';
        }

        if (is_string($items[$key])) {
            $this->item = $items[$key];
        } else {
            $this->item = '';
        }

        if (is_a(class: $expectedClass, object: $source)) {
            $this->source = $source;
        } else {
            $this->source = new NullLogger();
        }
    }
}

/**
 * A union wider than plain `bool` carries a value, not a branch selector, so it
 * is not a mode flag however it is branched on. shapes.php pins the two
 * spellings that *do* normalize to plain `bool`, `?bool` and `bool|null`.
 */
final class WideUnionParameter
{
    public function __construct(bool|string $mode)
    {
        if ($mode) {
            $this->mode = $mode;
        } else {
            $this->mode = 'default';
        }
    }
}

/**
 * The far side of the two boundaries shapes.php exercises: a group the flag
 * sits in, and a group standing in the expression, must both end where the
 * statement ends. Every constructor below puts a ternary in the *next*
 * statement, so a scan that ran past its own statement would report the flag.
 *
 * A group whose result no selector follows, a `match` operand ending its own
 * statement, a comma at statement level with no group around it at all, and a
 * comma-separated group feeding a guard clause — which is exempt through the
 * step-out exactly as it is anywhere else.
 */
final class NestedGroupEnds
{
    public function __construct(bool $verbose, int $retries, mixed $value)
    {
        $this->allowed = in_array($verbose, [true], true);
        $this->state = $retries > 3 ? 'capped' : 'open';

        $this->mode = $verbose && match ($value) { 'draft' => true, default => false };
        $this->label = $retries > 3 ? 'capped' : 'open';

        if ($retries > 3) {
            echo $verbose, PHP_EOL;
        }

        $this->level = $retries > 3 ? 'capped' : 'open';

        $this->transport = in_array($verbose, [true], true)
            ? throw new InvalidArgumentException('verbose is not a construction mode')
            : new Mailer();
    }
}

/**
 * Guards whose own branches all throw, each holding an unrelated construct of
 * the same kind inside what it throws — a `switch` in a closure the exception
 * message is built from, a `match` producing that message. The nested `case`
 * labels and `match` arms are branches of the nested construct, not of the
 * guard, so a walk that counted them as the guard's own would find a
 * non-throwing branch and report a guard that throws on every branch it has.
 */
final class NestedGuardBranches
{
    public function __construct(bool $verbose, string $mode)
    {
        $this->transport = match ($verbose) {
            true => throw new InvalidArgumentException(match ($mode) {
                'draft' => 'draft is not a construction mode',
                default => 'live is not a construction mode',
            }),
            default => throw new InvalidArgumentException('verbose is not a construction mode'),
        };

        switch ($verbose) {
            case true:
                throw new InvalidArgumentException($this->explain(function () use ($mode) {
                    switch ($mode) {
                        case 'draft':
                            return 'draft';
                        default:
                            return 'live';
                    }
                }));
            default:
                throw new InvalidArgumentException('verbose is not a construction mode');
        }
    }
}

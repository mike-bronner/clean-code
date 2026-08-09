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
 *      `match`, `switch`), coalesce defaults (`??` and the elvis `?:`) over a
 *      mode flag, a non-boolean parameter in a condition, a type predicate
 *      applied to a *derived* value rather than the parameter, all three
 *      signals inside a named constructor and inside an ordinary method, all
 *      three inside a closure and an arrow function declared in a constructor
 *      body, a `__constructor()` lookalike, a plain `function __construct()`
 *      at file scope, member calls that merely share a name with the argument
 *      readers, and every bodiless constructor shape.
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
 * body executes when it is called rather than during construction.
 */
final class NestsDeclarations
{
    public function __construct(bool $lazy, mixed $value)
    {
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

<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Constructors.NoLogic.
 *
 * Two things keep this file discriminating:
 *
 *   1. The compliant form of the construct the sniff registers on — a
 *      constructor whose body only assigns to its own properties, in every
 *      spelling the standard allows: plain assignment, promoted parameters,
 *      promoted parameters mixed with body assignments, a `??`/ternary default
 *      on the right-hand side, a subscript write to an own property, an
 *      exact `parent::__construct(…)` delegation in every spelling that really
 *      invokes it, and a body with no statements at all.
 *   2. Every near-miss shape the sniff must stay silent on: a plain function
 *      named `__construct` at file scope, a method whose name merely resembles
 *      `__construct`, an ordinary method full of logic, an abstract and an
 *      interface constructor with no body, and logic inside a closure, an
 *      arrow function and an anonymous class declared on an assignment's
 *      right-hand side.
 *
 * Dropping any one of the sniff's guards reddens this file.
 */

/**
 * A free function named __construct is legal PHP but is not a class
 * constructor: the OO-scope guard has to skip it, logic and all.
 */
function __construct(int $a): void
{
    doSomething($a);

    if ($a > 0) {
        echo $a;
    }
}

final class OnlyPropertyAssignments
{
    private int $a;

    private int $b;

    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}

final class EmptyConstructor
{
    public function __construct()
    {
    }
}

class PromotedPropertiesOnly
{
    public function __construct(
        private int $x,
        private string $y,
    ) {
    }
}

final class PromotedPlusBodyAssignments
{
    private int $sum;

    public function __construct(
        private int $x,
        private int $y,
    ) {
        $this->sum = $x;
    }
}

final class DelegatesToParent extends PromotedPropertiesOnly
{
    public function __construct()
    {
        parent::__construct(1, 'y');
    }
}

/**
 * Every argument list that really invokes the parent stays delegation, however
 * it is spelled. The spread is the near-miss for the first-class-callable
 * rejection: both open on an ellipsis, and only the spread carries an argument
 * after it, so treating any ellipsis as the callable syntax reddens this class.
 */
final class DelegatesToParentInEverySpelling extends PromotedPropertiesOnly
{
    public function __construct(array $args)
    {
        parent::__construct(...$args);
        parent::__construct(x: 1, y: 'y');
    }
}

final class DefaultedAssignmentRightHandSides
{
    private int $a;

    private string $b;

    private array $c;

    public function __construct(?int $a, ?string $b, ?array $c)
    {
        $this->a = $a ?? 0;
        $this->b = $b ? 'set' : 'unset';
        $this->c = $c['nested'] ?? [];
    }
}

final class SubscriptPropertyWrites
{
    private array $arr;

    private array $cfg;

    public function __construct(int $a, string $b)
    {
        $this->arr[] = $a;
        $this->cfg['key'] = $b;
    }
}

/**
 * The right-hand side is never inspected, so a declaration that merely *holds*
 * logic until someone calls it is an assignment like any other.
 */
final class DeferredLogicOnRightHandSide
{
    private $callback;

    private $doubler;

    private $collaborator;

    public function __construct(int $a)
    {
        $this->callback = function () use ($a): int {
            if ($a > 0) {
                return doSomething($a);
            }

            return 0;
        };
        $this->doubler = fn (int $v): int => $v * 2;
        $this->collaborator = new class {
            public function run(): void
            {
                doSomething(1);
            }
        };
    }
}

final class NeighbouringDeclarations
{
    private int $a;

    public function __constructor(int $a): void
    {
        doSomething($a);
    }

    public function __construct(int $a)
    {
        $this->a = $a;
    }

    public function configure(int $a): void
    {
        if ($a > 0) {
            doSomething($a);
        }

        foreach ([1, 2] as $x) {
            doSomething($x);
        }
    }
}

abstract class AbstractConstructor
{
    abstract public function __construct(int $a);
}

interface DeclaresConstructor
{
    public function __construct(int $a);
}

trait AssignsInConstructor
{
    private int $a;

    public function __construct(int $a)
    {
        $this->a = $a;
    }
}

/**
 * The near-misses for the invoking-target rejection. A subscript key reads
 * something without invoking it in every one of these spellings.
 *
 * The last three only look like an interpolation. `{\$…}` and `\${…}` escape the
 * dollar, which leaves the string with nothing to interpolate at all, so PHPCS
 * hands it over as an ordinary non-interpolating string token. The mixed one is
 * the case that reaches the check and still has to pass: `$key` interpolates for
 * real, so the token *is* the interpolated kind, while the `\${literal}` beside
 * it is escaped text that only reads like the syntax that would be rejected.
 */
final class ReadingAssignmentTargets
{
    private array $cfg;

    private string $prefix;

    public function __construct(string $key, string $value)
    {
        $this->cfg[$this->prefix] = $value;
        $this->cfg["$key"] = $value;
        $this->cfg["$this->prefix"] = $value;
        $this->cfg["plain text"] = $value;
        $this->cfg['single quoted'] = $value;
        $this->cfg[<<<'KEY'
        nowdoc interpolates nothing, not even {$this->key()}
        KEY] = $value;
        $this->cfg["{\$this->key()}"] = $value;
        $this->cfg["\${key}"] = $value;
        $this->cfg["$key \${literal}"] = $value;
        $this->cfg["\{$this->prefix}"] = $value;
        $this->cfg["\\\{$this->prefix}"] = $value;
    }
}

/**
 * A parenthesis in the assignment target only invokes when something callable
 * precedes it. After an operator, an opening bracket, or a separator there is
 * nothing to call, so the parenthesis groups a sub-expression and the write
 * stays a plain property assignment — it reads the object's own state to build
 * a key and calls nothing while doing it.
 *
 * The `(int)` case is here because PHPCS gives a cast as one token: the
 * parenthesis that follows it is the grouping one, not the cast's own.
 */
final class GroupingParenthesesInAssignmentTargets
{
    private array $items;

    private int $a;

    private int $b;

    private string $prefix;

    private $other;

    public function __construct($value)
    {
        $this->items[($this->a + $this->b)] = $value;
        $this->items[(($this->a))] = $value;
        $this->items[-($this->a)] = $value;
        $this->items[+($this->a)] = $value;
        $this->items[~($this->a)] = $value;
        $this->items[!($this->a)] = $value;
        $this->items[@($this->a)] = $value;
        $this->items[$this->prefix . ($this->a)] = $value;
        $this->items[$this->a ** ($this->b)] = $value;
        $this->items[$this->a === ($this->b)] = $value;
        $this->items[$this->a && ($this->b)] = $value;
        $this->items[$this->a ? ($this->a) : ($this->b)] = $value;
        $this->items[$this->a ?? ($this->b)] = $value;
        $this->items[$this->other instanceof ($this->prefix)] = $value;
        $this->items[(int) ($this->a)] = $value;
        $this->items[[$this->a, ($this->b)][0]] = $value;
        $this->items[[1 => ($this->a)][1]] = $value;
        $this->items[[($this->a)][0]] = $value;
    }
}

/**
 * The right-hand side stays uninspected under the invoking-target rejection
 * too: the scan stops at the assignment operator, so an interpolation that
 * really does hold a call is beyond where it looks.
 */
final class InterpolationOnRightHandSide
{
    private string $label;

    public function __construct()
    {
        $this->label = "{$this->key()}";
    }

    private function key(): string
    {
        return 'k';
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff;

class Route
{
    public function __construct(string $path, ?string $name = null)
    {
    }
}

class Middleware
{
    public function __construct(string $alias, ?int $priority = null)
    {
    }
}

// An attribute instantiates its class, so its arguments are the constructor's
// — and the class is named outright, so the fix is safe.
#[Route('/users', null)]
class UsersController
{
}

// The same holds for each attribute in a group, including the ones that follow
// a comma rather than the opening `#[`.
#[Route('/admin', null), Middleware('auth', null)]
class AdminController
{
}

class Mailer
{
    public function send(string $body, ?string $subject = null, ?array $cc = null): void
    {
    }

    public static function make(?string $name = null): self
    {
        return new self();
    }
}

/**
 * `final`, so no subclass can intercept a call dispatched at runtime: every
 * call below reaches the declaration written here and the fixer can name its
 * parameter.
 */
final class Notifier
{
    public function __construct(?Mailer $mailer = null, ?string $channel = null)
    {
    }

    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public function collect(?string $first = null, ...$rest): void
    {
    }

    public function run(): void
    {
        // Method call on $this, plain and nullsafe.
        $this->relay('body', null);
        $this?->relay('body', null);

        // Static call on a class declared in this file.
        Mailer::make(null);
        self::helper(null);

        // Late static binding, contained by the final class.
        static::helper(null);

        // Constructor calls.
        new Notifier(null);
        new self(null);

        // Multiple null arguments in a single call.
        new Notifier(null, null);

        // A null that skips an optional parameter positioned before further
        // positional arguments.
        new Notifier(null, 'sms');

        // Not auto-fixable: the argument after the flagged null is unpacked
        // from a spread, so it cannot be given a name — whether it lands in a
        // variadic parameter...
        $this->collect(null, ...$this->rest());

        // ...or in ordinary ones.
        $this->pair(null, ...$this->rest());

        // Not auto-fixable either: the argument after the flagged null lands
        // in a variadic parameter, which a named argument cannot address.
        $this->collect(null, 'extra');
    }

    public function pair(?string $left = null, ?string $right = null): void
    {
    }

    public static function helper(?int $level = null): void
    {
    }

    public function rest(): array
    {
        return [];
    }
}

/**
 * Not final. A subclass — which normally lives in a file this sniff never sees
 * — may override any overridable method and rename its parameters, so a call
 * dispatched against the runtime class is reported but left unfixed. Only the
 * declarations dispatch cannot bypass stay fixable.
 */
class Extendable
{
    public function __construct(?string $tag = null)
    {
    }

    public function run(): void
    {
        // Reported, not fixable: an override may rename $flag.
        $this->relay('body', null);

        // Reported, not fixable: `static::` resolves against the runtime class.
        static::helper(null);

        // Reported, not fixable: `new static()` constructs the runtime class.
        new static(null);

        // Fixable: a private method is resolved in the scope that declares it,
        // so `$this->` reaches this one whatever a subclass declares.
        $this->conceal(null);

        // Reported, not fixable: `static::` binds to the subclass first and
        // only then checks visibility, so `private` does not protect it.
        static::hidden(null);

        // Fixable: a final method cannot be overridden at all.
        $this->seal(null);
        static::sealed(null);

        // Fixable even though the class is extendable: `self` is not
        // late-bound, so it names this class and reaches these declarations
        // whatever a subclass overrides.
        self::relay('body', null);
        new self(null);
    }

    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public static function helper(?int $level = null): void
    {
    }

    private function conceal(?string $secret = null): void
    {
    }

    private static function hidden(?string $secret = null): void
    {
    }

    final public function seal(?string $lid = null): void
    {
    }

    final public static function sealed(?string $lid = null): void
    {
    }
}

/**
 * Not final, but the constructor is, so `new static()` cannot reach another
 * one.
 */
class SealedConstructor
{
    final public function __construct(?string $tag = null)
    {
    }

    public static function make(): static
    {
        return new static(null);
    }
}

/**
 * A trait's methods are copied into every using class, which may declare its
 * own version of any of them — `private` and `final` included. Nothing written
 * in a trait proves which body runs, so none of these are fixable.
 */
trait Copied
{
    public function __construct(?string $tag = null)
    {
    }

    public function run(): void
    {
        $this->target(null);
        $this->concealed(null);
        $this->sealed(null);

        // `self` does not name the trait. The trait is flattened into the
        // using class and `self` names *that* class, whose own declaration of
        // a method wins over the copied one — so `self::` proves no more here
        // than the `$this->` calls above, and `new self()` no more than
        // `new static()`.
        self::target(null);
        new self(null);
    }

    public function target(?string $mode = null): void
    {
    }

    private function concealed(?string $mode = null): void
    {
    }

    final public function sealed(?string $mode = null): void
    {
    }
}

/**
 * An anonymous class written *inside* a trait is a class scope of its own:
 * `self` there names the anonymous class, which nothing can extend, so the
 * call stays fixable even though a trait encloses it.
 */
trait Builds
{
    public function build(): object
    {
        return new class () {
            public function run(): void
            {
                self::adjust(null);
            }

            public function adjust(?string $knob = null): void
            {
            }
        };
    }
}

/**
 * An enum cannot be extended, so `$this->` reaches the method written here.
 */
enum Mode
{
    case Fast;

    public function run(): void
    {
        $this->target(null);
    }

    public function target(?string $mode = null): void
    {
    }
}

class Factory
{
    public function build(): object
    {
        // An anonymous class has no name, so nothing can extend it — the call
        // below is fixable even though the enclosing Factory is not final.
        return new class () {
            public function run(): void
            {
                $this->adjust(null);
            }

            public function adjust(?string $knob = null): void
            {
            }
        };
    }
}

function dispatch(string $to, ?int $retries = null): void
{
}

// Standalone function call.
dispatch('a', null);

# Classes: Introspection / Type Casting

## Standard

- Avoid introspection (checking the type of the class to determine the outcome
  of a condition), e.g. using `instanceof`. This creates tight coupling and
  introduces technical debt, as the object type should already be defined in
  the method parameter or class property. If you have loosely coupled code but
  use introspection, you introduce another point of failure. Reaching for
  introspection probably means logic should be encapsulated or refactored.

**Why:**

- Causes brittle code (tech debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff matches this standard, and each near
neighbour was evaluated against the test suite before a custom sniff was
written:

- Slevomat
  [`PHP.TypeCast`](https://github.com/slevomat/coding-standard/blob/master/doc/php.md#slevomatcodingstandardphptypecast-)
  normalises cast *syntax* (`(boolean)` → `(bool)`) — a formatting rule that
  says nothing about branching on a type.
- Slevomat
  [`Classes.ModernClassNameReference`](https://github.com/slevomat/coding-standard/blob/master/doc/classes.md#slevomatcodingstandardclassesmodernclassnamereference-)
  rewrites `get_class($x)` to `$x::class`; it *promotes* introspection rather
  than flagging it.
- `Generic.PHP.ForbiddenFunctions` can ban `get_class()`/`gettype()` outright,
  but it has no notion of context, so it also flags the reporting uses this
  standard permits (exception messages, log lines, assertions) — bending the
  test suite to fit it was rejected.

The standard is therefore enforced by the custom
`CleanCode.Classes.DisallowTypeIntrospection` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#73](https://github.com/mike-bronner/phpcs-rules/issues/73)).

- **Detection** — type introspection is flagged *only where it decides which
  branch runs*, reported at the offending token:
  - `instanceof` →
    `CleanCode.Classes.DisallowTypeIntrospection.InstanceOf`
  - `get_class()`, `get_debug_type()`, `gettype()`, `is_a()`,
    `is_subclass_of()` →
    `CleanCode.Classes.DisallowTypeIntrospection.IntrospectionFunction`

  The branch-deciding positions are the condition of an `if`, `elseif`, or
  `while`; the subject of a `switch` or `match`; a `switch` `case` label; a
  `match` arm condition (including every condition of a multi-condition arm);
  and the condition of a ternary.

- **Not flagged** — introspection that *reports* a type instead of branching on
  it is deliberately left alone:
  - **Exception messages and log lines** —
    `throw new RuntimeException('Unsupported: ' . get_class($value))`.
  - **Assertions** — `assert($value instanceof Throwable)`.
  - **Predicates** — `return $value instanceof Throwable;`. The method reports
    a type; its caller decides what to do with the answer.
  - **Predicates written inline** — `if (array_filter($rows, fn ($r) => $r
    instanceof Failure))`. Any function body bounds the search, so a check
    inside one is a predicate deciding that body's *return value*, even when the
    body is an argument inside an enclosing condition. The keyword that opened
    the body is not what decides this — all three forms play the same role:

    ```php
    if (array_filter($rows, fn ($r) => $r instanceof Failure)) { }
    if (array_filter($rows, function ($r) { return $r instanceof Failure; })) { }
    if (array_filter($rows, new class {
        public function __invoke($r) { return $r instanceof Failure; }
    })) { }
    ```

    A branch written *inside* the body is still flagged — the boundary limits
    which branches a check is measured against, it does not exempt the body.
  - **Branch *bodies*** — a `get_class()` inside an `if` block, a `case` body,
    or a `match` arm's result, rather than in the condition that selected it.
  - **Same-named methods and functions** — `$this->gettype($value)`,
    `Vendor\get_class($value)`, and a `function gettype()` declaration are not
    the global introspection functions. Nor is a bare name the file resolves to
    something of its own: a `use function Vendor\get_class;` import (under its
    own name or an `as` alias), or a `function get_class()` declared in the
    file's namespace. A root-namespaced `\get_class()` *is* the global
    function — an explicit qualifier outranks any import — and is flagged.
  - **First-class callables** — `array_map(get_class(...), $values)`. The
    `name(...)` syntax builds a `Closure` referring to the function rather than
    calling it, so nothing is introspected where it is written; like a callback
    predicate, the caller that eventually invokes it decides what to do with
    each answer. A variadic unpack (`is_a(...$args)`) *is* a call and is
    flagged.

- **Auto-fixable — No (detection only).** Removing a type check means moving
  the behaviour onto the object (polymorphism) or narrowing a signature so the
  type is declared rather than interrogated, and then updating call sites. That
  is a refactor, not a token rewrite, so the sniff reports and leaves the change
  to the developer.

Tests covering compliant code, per-line/column violation reporting for
`instanceof` and for each introspection function, the non-branching boundary
cases, the name-resolution cases above (imports, aliases, file-local
declarations, first-class callables — each paired with a control that must still
be reported), and the non-fixable (detection-only) guarantee live at
`tests/Standards/DisallowTypeIntrospectionTest.php`, with fixtures under
`tests/fixtures/DisallowTypeIntrospectionSniff/`.

The scope rule gets the whole grid rather than a sample, in both directions:
`function-scopes.php` writes each function-body form into each branch-deciding
position and asserts silence, and `function-scope-branches.php` puts a real
branch inside those same bodies and asserts every one is still reported.

## What remains code review

Every gap below is **under-detection** — introspection the sniff stays silent
about. That is deliberate: a linter that flags correct code gets switched off,
so wherever the token stream cannot settle the question the sniff says nothing.
It knowingly keeps **no over-detection**; a false positive is a bug, and should
be reported as one.

- **Deciding whether the refactor is polymorphism or a narrower signature.**
  The sniff points at the type check; choosing between pushing behaviour onto
  the object, introducing an interface, or simply type-hinting the parameter is
  a design call.
- **Introspection the sniff cannot see.** `$value::class` used in a condition,
  the `is_string()`/`is_int()` family of primitive predicates, and a type check
  hidden behind a helper method (`if ($this->isThrowable($value))`) all read as
  ordinary expressions to a token-based sniff. They are the same smell and are
  still worth flagging in review.
- **Introspection reached through a value rather than a name.**
  `$fn = 'get_class'; if ($fn($value) === …)` and
  `\Closure::fromCallable('get_class')` name the function in a *string*, and
  which function a variable holds at the call site is a data-flow question no
  token-based sniff can answer. Unreachable by design, not an oversight.
- **`for` loops.** Their parentheses hold the initialiser and the increment
  alongside the condition, and a token-level check cannot tell them apart, so
  the sniff leaves `for` alone.
- **Several namespaces in one file.** Shadowing (an import or a declared
  function) is resolved against the file as a whole, so a name shadowed in one
  namespace block is treated as shadowed in all of them. PSR-1 rules the shape
  out, and the cost is a missed report rather than a false one.

# Constructors: No Logic in Constructors

## Standard

Constructors should not include any functionality or logic, but merely assign
values to object properties. If logic needs to be performed, that is an
indication the information passed in should actually be another object. Any code
in the constructor is parsed every time an object is created, regardless of
necessity, and can't be optimized. If only assignments are handled, optimization
can be controlled.

**Why:** prevents optimization (technical debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff flags non-assignment statements inside a
constructor body. Slevomat's
[`Classes.RequireConstructorPropertyPromotion`](https://github.com/slevomat/coding-standard/blob/master/doc/classes.md#requireconstructorpropertypromotion)
(already wired in for [#47](https://github.com/mike-bronner/phpcs-rules/issues/47))
pushes assignments into promoted parameters but says nothing about other logic,
and was evaluated against this standard's test suite without matching it. The
standard is therefore enforced by the custom `CleanCode.Constructors.NoLogic`
sniff, wired into the master `rules.xml` via the CleanCode standard
([#40](https://github.com/mike-bronner/phpcs-rules/issues/40)).

- **Detection** — the sniff walks the top-level statements of every
  `__construct()` body and flags each statement that is not one of the two
  allowed forms, reporting at the statement's first token as
  `CleanCode.Constructors.NoLogic.LogicFound`. Allowed:
  - a **property assignment** — a statement beginning with `$this->…` and
    carrying a plain `=` operator at its top level whose target is a direct
    property chain on `$this` (`$this->foo = …;`, `$this->arr[] = …;`,
    `$this->cfg['k'] = …;`). The right-hand side is not inspected, so defaulting
    with `??` or a ternary (`$this->foo = $foo ?? 0;`) stays compliant.
  - a **`parent::__construct(...)` call** — delegating to the parent constructor
    is assignment, not logic.
- **Flagged** — everything else in the body:
  - **Control structures** — `if`/`elseif`/`else`, `for`, `foreach`, `while`,
    `do … while`, `switch`, and `try … catch … finally`. Each construct is
    reported **once**, at its opening keyword; continuation clauses and nested
    statements are not reported separately.
  - **`throw` statements**, **method calls** (`$this->configure()`) and
    **function calls** (`doSomething()`).
  - **Non-property assignments** — a local-variable assignment (`$x = …;`) is
    intermediate computation, not object state, and an increment (`$this->n++;`)
    computes rather than assigns.
  - **Assignments whose target contains a call** — a call in the assignment
    *target* (`$this->getConfig()->value = …;`, `$this->items[$this->key()] = …;`,
    `$this->loadDefaults()['k'] = …;`) runs logic on every instantiation, so it
    is flagged even though the statement ends in an assignment. Only the target
    is inspected — a call on the *right-hand side* (`$this->foo = compute();`)
    stays outside the sniff's scope, as noted above.
- **Compliant edge cases** — an **empty constructor**, a constructor with only
  **promoted-property parameters** (no body), a constructor **mixing promoted
  parameters with body assignments**, a constructor **calling only
  `parent::__construct(...)`**, and assignments whose RHS uses **null-coalescing
  (`??`) or a ternary** default all pass.
- **Auto-fixable — No (detection only).** Moving logic out of a constructor is a
  refactor, not a mechanical rewrite: the code has to land somewhere deliberate
  (a named constructor, a factory method, or a collaborator object), and that
  target cannot be inferred from tokens. The sniff surfaces the violation and
  leaves the refactor to the developer.

### Known boundaries

A few intentional edges, decided rather than accidental:

- **A free `function __construct()`** (a function at namespace scope, not a
  class method) is **not** a constructor and is never inspected — the sniff
  guards on the declaration living inside an object-oriented container.
- **List-destructuring straight into properties** (`[$this->a, $this->b] = $pair;`)
  is **flagged**: the statement begins with `[`, not `$this->`, so it does not
  match the property-assignment form. Uncommon in constructors and treated as
  logic by design.
- **Explicit-ancestor delegation** (`ParentClass::__construct(...)`, naming the
  class instead of using the `parent` keyword) is **flagged**; only the
  `parent::__construct(...)` form is recognised as delegation.

Ruleset-integration tests covering compliant code (including array-subscript
property writes and a skipped free `__construct` function), per-line violation
reporting for every non-assignment statement kind (control structures including
`for`, calls, increments, non-property and call-in-target assignments, `throw`),
once-per-construct reporting across the `if … elseif … else`, `do … while`, and
`try … catch … finally` chains, and the non-fixable (detection-only) guarantee
live at `tests/Ruleset/NoLogicTest.php`.

## What remains code review

Nothing about *detecting* constructor logic — that is fully machine-enforced.
The *refactor* to relocate flagged logic (introducing a named constructor or
factory, extracting a collaborator, threading the result to call sites) is
manual work the sniff surfaces but does not perform.

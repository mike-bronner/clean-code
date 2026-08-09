# Constructors: Primary + Named Constructors

## Standard

- Use one primary constructor (`__construct`).
- Provide multiple secondary (named) constructors — static factory methods —
  for the different scenarios in which the object is created.
- Every named constructor makes use of the primary constructor, so
  initialization logic lives in exactly one place.

**Why:** accommodate different construction scenarios without repeating code
(DRY).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniffs)

The standard's *design judgement* is semantic — whether a class should offer
named constructors at all, and whether cross-file construction paths
(builders, factories, DI container definitions) respect the single primary
constructor, stay with code review. But both trigger patterns that violate
the standard **are statically lintable**, and each is tracked in a focused
sniff issue.

### Slice 1 — named-constructor delegation check ([#184](https://github.com/mike-bronner/phpcs-rules/issues/184))

**Enforced** by the custom `CleanCode.Constructors.PrimaryConstructorDelegation`
sniff, which reports the single code `Missing`.

- **Detection** — a `static` method whose declared return type is `self`,
  `static`, or the declaring class name is recognizably a named constructor.
  Its body should contain a `new self(...)` / `new static(...)` /
  `new <DeclaringClass>(...)`, or a static call delegating to another method
  of the same class. A body with neither obtains its instance while bypassing
  the primary constructor (e.g. `unserialize()`, reflection instantiation)
  and gets flagged. The nullable spellings a `tryFrom()` carries — `?self`,
  `self|null` — are named constructors too.
- **Delegation counts at the breadth of *any* static method of the declaring
  class**, not only another named constructor. A named constructor calling a
  plain private static helper that itself does `new self(...)` still routes
  through the primary constructor, one hop further out; the narrower reading
  would report that shape for no defect.
- **Warning severity, not error** — legitimate patterns return a stored
  instance (singleton/registry accessors on the warm path), so the sniff
  points at delegation candidates rather than mandating a fix.
- **Reported on the `function` keyword**, once per method: the defect is the
  absence of delegation across the whole body, so it has no statement of its
  own to point at.
- **Detection only.** Routing a body through the primary constructor means
  deciding which parameter each local value feeds, which is not a mechanical
  rewrite.

Out of scope, and why:

- **Instance methods**, whatever they return. A `withX()` wither returning
  `self` modifies a copy of an existing object; it constructs nothing.
- **Bodiless methods** — an abstract declaration or an interface signature has
  no body in which delegation could appear.
- **Enum methods.** `new` on an enum is a fatal error, so an enum's named
  constructor can only return a case or the engine's own `from()`/`tryFrom()`.
  Flagging it would state a requirement the language forbids satisfying.

Two limits on what counts as delegation, both erring toward reporting rather
than staying silent:

- The class reference must be **unqualified** — `self`, `static`, or the bare
  class name. `new \Other\Money()` in a file declaring `Money` names a
  different class far more often than the same one, and a sniff handed one file
  cannot resolve which.
- A named constructor calling **itself** does not delegate: without a `new`
  anywhere in the recursion it never reaches a constructor.

`new self(...)` written inside an **anonymous class** declared in the body does
not count either — `self` there names the anonymous class. Closures and arrow
functions keep the enclosing class binding, so those are walked into.

### Slice 2 — combined-constructor detection ([#193](https://github.com/mike-bronner/phpcs-rules/issues/193))

A primary constructor that merges multiple construction scenarios into one
body is the standard's other violation shape, and its mode-switching signals
are token-visible inside `__construct`:

- **Mode-flag branching** — a `bool` parameter (or one defaulting to
  `true`/`false`) used in the condition of an `if`, `switch`, `match`, or
  ternary that selects between initialization paths.
- **Parameter-type switching** — a parameter tested with `instanceof` or a
  type predicate (`is_string()`, `is_array()`, …) in a branching condition;
  the constructor accepts "either X or Y" and branches on which arrived.
- **Poor-man's overloading** — `func_num_args()` / `func_get_args()` in the
  constructor body.
- **Warning severity, not error** — branching in a constructor is a design
  smell, not always a defect; the sniff points at split-into-named-constructors
  candidates. Guard clauses (branches that only throw) and coalesce defaults
  (`$x ?? new Default()`) stay out.

**Considered and rejected:** a naming-prefix check on named constructors
(`from*`, `create*`, `make*`, …). PHP has no canonical prefix vocabulary —
`of()`, `parse()`, and the enum-native `from()`/`tryFrom()` are all idiomatic
— so the check would enforce an arbitrary word list rather than the standard's
actual substance (delegation / DRY).

## What remains code review

The judgement the sniffs cannot make: whether a class *should* offer named
constructors where multiple construction scenarios exist, whether a named
constructor quietly duplicates initialization logic after delegating, and
whether cross-file construction paths (builders, factories, DI container
definitions) respect the single primary constructor. The sniffs in
[#184](https://github.com/mike-bronner/phpcs-rules/issues/184) and
[#193](https://github.com/mike-bronner/phpcs-rules/issues/193) catch the
mechanical violation shapes; the design judgement stays with review.

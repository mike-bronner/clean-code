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

- **Detection** — a `static` method whose declared return type is `self`,
  `static`, or the declaring class name is recognizably a named constructor.
  Its body should contain a `new self(...)` / `new static(...)` /
  `new <DeclaringClass>(...)`, or a static call delegating to another method
  of the same class. A body with neither obtains its instance while bypassing
  the primary constructor (e.g. `unserialize()`, reflection instantiation)
  and gets flagged.
- **Warning severity, not error** — legitimate patterns return a stored
  instance (singleton/registry accessors on the warm path), so the sniff
  points at delegation candidates rather than mandating a fix.

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

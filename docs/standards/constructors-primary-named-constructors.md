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
- **A trait method return-typed to its eventual consumer by name.** A trait is
  compiled into whichever class uses it, and one file never says which class
  that is, so `: Money` inside `trait Zeroable` matches neither `self`/`static`
  nor the trait's own name and is not recognized as a named constructor,
  however its body builds the instance. Nothing available in a single file
  resolves it. A trait method typed `self` or `static` — the spelling that
  names no class — is inspected in full: `new self(...)` in its body reaches
  the consuming class's primary constructor at use-time, so the same
  delegation question applies and the sniff asks it.

Two limits on what counts as delegation, both erring toward reporting rather
than staying silent:

- The class reference must carry **no namespace segment** — `self`, `static`,
  the bare class name, or the root-qualified spelling of that name in a file
  declaring no namespace, where the two are one class. A name with a segment
  in it cannot be resolved from a single file, wherever this class's name sits
  in it: `new \Other\Money()` in a file declaring `Money` names a different
  class far more often than the same one, `new Money\Amount()` and
  `new \Money\Amount()` name one that merely sits under a namespace spelled
  the same, and under `namespace App` so does `new \Money()`. The return type
  is read the same way, so `zero(): \Money` on a class `Money` is the named
  constructor it looks like.
- A named constructor calling **itself** does not delegate: without a `new`
  anywhere in the recursion it never reaches a constructor.

`new self(...)` written inside an **anonymous class** declared in the body does
not count either — `self` there names the anonymous class. Closures and arrow
functions keep the enclosing class binding, so those are walked into.

### Slice 2 — combined-constructor detection ([#193](https://github.com/mike-bronner/phpcs-rules/issues/193))

Implemented by `CleanCode.Constructors.DisallowCombinedConstructor`.

A primary constructor that merges multiple construction scenarios into one
body is the standard's other violation shape, and its mode-switching signals
are token-visible inside `__construct`. Each signal reports under its own code,
so a consuming ruleset can tune the three independently:

| Code | Signal |
|---|---|
| `ModeFlag` | a `bool` parameter (or one defaulting to `true`/`false`) used in the condition of an `if`, `elseif`, `switch`, `match`, or ternary that selects between initialization paths |
| `TypeSwitch` | a parameter tested with `instanceof` or a type predicate (`is_string()`, `is_array()`, `gettype()`, …) in a branching condition — the constructor accepts "either X or Y" and branches on which arrived |
| `ArgumentCount` | `func_num_args()` / `func_get_args()` anywhere in the constructor body |

A construct's "condition" is read the way that construct spells it: the
parenthesised expression of an `if`, `elseif`, `switch`, or `match`; the
expression in front of a ternary `?`; and — for the two dispatch idioms that
put the test in the branch rather than in the head — a `match` arm's condition
and a `switch`'s `case` labels.

A predicate tests its *first* argument and nothing else, so
`is_a($value, $expectedClass)` and `is_subclass_of($value, $expectedClass)`
report `$value` alone — the class name they compare it against is a value the
call reads, not a parameter whose own type is switched on. The parameter also
has to be the *whole* of that first argument: a predicate applied to a derived
value is not a signal, whether the value is derived by a call
(`is_string(trim($value))`), a property read (`is_string($holder->prop)`), or a
subscript (`is_string($items[$key])`).

**Warning severity, not error.** Branching in a constructor is a design smell,
not always a defect; the sniff points at split-into-named-constructors
candidates. It is detection-only: splitting a constructor rewrites the class's
construction API and every call site, which is not a mechanical rewrite.

**Why a custom sniff.** No existing PHPCS or Slevomat sniff reports a
constructor that branches on *how it was called*.
`SlevomatCodingStandard.Functions.FunctionLength` and the bundled
cyclomatic-complexity metrics count statements and paths without caring which
method they sit in or what the branch tests; Slevomat's constructor sniffs speak
about property promotion. The closest neighbour is this package's own
`CleanCode.Functions.DisallowBooleanArgumentFlag`, which reports a boolean flag
in *any* declaration's parameter list — a different finding: a constructor that
takes a flag and never branches on it is that sniff's alone, and a type switch
or a `func_get_args()` carries no flag parameter for it to see.

Deliberately silent on:

- **Guard clauses** — a branch whose first statement is a `throw` validates a
  precondition rather than selecting an initialization path, so its condition
  is exempt whatever signal it carries — a mode flag, a type test, or an
  argument-list read alike, since `if (func_num_args() > 1) { throw … }`
  rejects a call rather than choosing how to build one. A construct whose own
  condition stands in front of every branch — a `switch` or `match` subject, a
  ternary's condition — has no branch of its own to judge, so it qualifies when
  at least one branch throws and no more than one survives: the rest reject,
  and the single surviving branch is the one construction path.
- **Coalesce defaults** — `$this->x = $x ?? new Default();` carries no
  branching token at all, and the elvis `?:` supplies a default for one
  expression rather than selecting between two. `is_null()` is left out of the
  predicate list on the same grounds.
- **Anything but `__construct`** — named constructors and ordinary methods
  belong to slice 1, and a `function __construct()` that is not a class member
  constructs nothing.
- **Bodiless constructors** — abstract and interface declarations, and
  promotion-only bodies with no statements in them.
- **Nested declarations** — a named function, closure, arrow function, or
  anonymous class declared in the body runs on its own terms;
  `func_get_args()` inside a closure reads the *closure's* arguments. Only the
  *body* of an anonymous class is exempt: the arguments in
  `new class ($legacy ? … : …) {}` are evaluated by the constructor that writes
  them, so a mode signal there still reports.
- **Named-argument predicate calls** — `is_a(object: $source, class: $c)`
  addresses its subject by name rather than by position. Resolving that needs a
  per-predicate table of parameter names, so the sniff stays silent: a missed
  warning on an exotic spelling costs less than a wrong one on a common
  spelling.

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

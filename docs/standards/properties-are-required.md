# Properties: Are Required

## Standard

- Avoid classes that do not encapsulate any data. A class without properties
  has no state and no identity — it is analogous to procedural, non-object-
  oriented code.

**Why:**

- Classes represent concepts, and every concept has attributes (mental debt).
- A stateless "class" is really a bag of functions; modelling it as a class
  creates an expectation of identity the code never fulfils.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff flags a class that declares zero
properties. Slevomat's class sniffs address related but different concerns —
`Classes.RequireConstructorPropertyPromotion` (how properties are declared),
`Classes.ForbiddenPublicProperty` (property visibility),
`Classes.ClassStructure` (member ordering) — and none reports the *absence* of
state. The PHPCS/Squiz/Generic standards have no equivalent either. The
standard is therefore enforced by the custom
`CleanCode.Classes.RequireProperties` sniff, wired into the master `rules.xml`
via the CleanCode standard
([#55](https://github.com/mike-bronner/phpcs-rules/issues/55)).

- **Detection** — every `class` that encapsulates no state is reported at the
  class declaration keyword as
  `CleanCode.Classes.RequireProperties.MissingProperty`. This includes the
  empty stub class (no members at all), since it too has no state.
- **Counts as state** — a conventional member variable (`private int $total;`),
  instance or `static`; a constructor-promoted parameter (`__construct(private
  int $total)`); an `extends` clause; and a trait `use` in the class body.
  Promotion declares a real instance property, so a class whose only state is
  promoted is compliant — which also keeps the sniff consistent with the
  enforced *Constructors: Property Promotion* standard.
- **Does not count** — plain (non-promoted) constructor or method parameters
  (they are arguments, not stored state), class constants (not the target of
  this standard), an `implements` clause (an interface declares no instance
  state to inherit), and properties, `extends` clauses or trait uses belonging
  to a nested or anonymous class inside a method (they are not this class's).
- **Not flagged** — interfaces and traits cannot declare instance state the way
  a class does, and enums carry identity through their cases; the sniff
  registers only on `T_CLASS`, so all three (and anonymous classes) are
  excluded.
- **Auto-fixable — No (detection only).** A class with no state cannot be given
  meaningful state mechanically: which property models the missing concept is a
  design decision, not a token rewrite. The sniff surfaces the gap and leaves
  the fix to the developer.

### Why inherited and composed state count

`extends` and `use` are read as state deliberately, and the choice is the
repository owner's rather than the sniff author's. The narrower reading — only
a class's *own* declaration counts — flags two very common shapes that plainly
do hold data:

```php
class NotFoundException extends HttpException {}   // state lives in the parent
class Post { use HasTimestamps; }                  // state lives in the trait
```

Reporting those serves the letter of "declares no properties" and contradicts
the rationale above, which is about a class having *no state*, so the wider
reading wins.

It is a heuristic, and knowingly so: a single-file sniff cannot confirm the
parent or the trait really declares anything, so a class composing a genuinely
stateless trait slips through. The hole is small — a stateless parent declares
no properties of its own and is flagged in its own right, so at least one class
in every inheritance chain has to own state.

### Known gap: this package's own sniff classes

A PHP_CodeSniffer sniff is a stateless strategy object — constants and methods,
no data — so this standard reports 26 of this package's own classes, including
the sniff that implements it. The rule is right about them; bringing them into
compliance is a package-wide refactor with its own issue, so until that lands
the package does not pass this one standard against itself. The one test that
asserts a sniff file's own cleanliness
(`tests/Standards/ManualModelResolutionTest.php`) pins that single expected
violation by name rather than silencing the rule.

Behaviour tests covering compliant code (instance, static, promoted, inherited
and trait-composed state), per-line violation reporting for a methods-only
class and an empty stub, the exclusion of interfaces/traits/enums, the
existing-sniff search, and the non-fixable (detection-only) guarantee live at
`tests/Standards/RequirePropertiesTest.php`, with fixtures in
`tests/fixtures/RequirePropertiesSniff/`.

## What remains code review

Nothing about *detecting* a stateless class — that is fully machine-enforced.
Choosing the right properties to model a concept, and whether a genuinely
data-free helper should be a class at all, remains design judgement the sniff
surfaces but does not make.

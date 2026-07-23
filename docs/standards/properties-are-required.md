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

- **Detection** — every `class` that declares no instance or static property is
  reported at the class declaration keyword as
  `CleanCode.Classes.RequireProperties.MissingProperty`. This includes the
  empty stub class (no members at all), since it too has no state.
- **Counts as a property** — a conventional member variable (`private int
  $total;`), instance or `static`, **and** a constructor-promoted parameter
  (`__construct(private int $total)`). Promotion declares a real instance
  property, so a class whose only state is promoted is compliant; this also
  keeps the sniff consistent with the enforced *Constructors: Property
  Promotion* standard.
- **Does not count** — plain (non-promoted) constructor or method parameters
  (they are arguments, not stored state), class constants (not the target of
  this standard), and properties belonging to a nested or anonymous class
  inside a method (they are not members of the outer class).
- **Not flagged** — interfaces and traits cannot declare instance state the way
  a class does, and enums carry identity through their cases; the sniff
  registers only on `T_CLASS`, so all three (and anonymous classes) are
  excluded.
- **Auto-fixable — No (detection only).** A class with no state cannot be given
  meaningful state mechanically: which property models the missing concept is a
  design decision, not a token rewrite. The sniff surfaces the gap and leaves
  the fix to the developer.

Ruleset-integration tests covering compliant code (instance, static, and
promoted properties), per-line violation reporting for a methods-only class and
an empty stub, the exclusion of interfaces/traits/enums, and the non-fixable
(detection-only) guarantee live at `tests/Ruleset/RequirePropertiesTest.php`.

## What remains code review

Nothing about *detecting* a stateless class — that is fully machine-enforced.
Choosing the right properties to model a concept, and whether a genuinely
data-free helper should be a class at all, remains design judgement the sniff
surfaces but does not make.

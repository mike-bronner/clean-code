# Pattern: SOLID

## Standard

- **Single Responsibility**: a class should have one and only one reason to
  change (a Model defines relationships/scopes/attributes; a Controller handles
  request/response; an Action is a single-purpose invokable).
- **Open-Closed**: objects should be open for extension but closed for
  modification.
- **Liskov Substitution**: classes and their sub-classes should be substitutable
  without breaking code.
- **Interface Segregation**: clients shouldn't be forced to depend on methods
  they don't use; split interfaces when signatures don't apply to all
  implementers.
- **Dependency Inversion**: depend on abstractions, not concretions; specify the
  interface instead of the concrete class (Action classes are a good example).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (core), three principles partially enforced today

The **core** of all five principles is architectural: whether a class has one
reason to change, whether a hierarchy is behaviourally substitutable, whether a
dependency is the right abstraction. Those are judgements about design intent
and cross-file relationships, and a PHPCS sniff reads one file's tokens at a
time with no symbol table and no project index. That core stays with code
review, which is what Tier 3 means here — per this repo's own tier definition,
the tier describes the standard's core, not the slices a rule may reach.

"Not statically enforceable" is **not** the whole story, and the assessment
below no longer says it is. **All five** principles turned out to carry a
token-visible slice. Being precise about what that means today:

- **Three are enforced now** — Single Responsibility, through seven shipped
  size and coupling sniffs; Open-Closed, through
  `CleanCode.Conditionals.TypeDiscriminatorDispatch`
  ([#324](https://github.com/mike-bronner/phpcs-rules/issues/324)); and
  Dependency Inversion, through
  `CleanCode.Classes.DisallowConstructorInstantiation`. All are wired into the
  master `rules.xml`.
- **Two are accepted but not yet built** — Liskov Substitution
  ([#131](https://github.com/mike-bronner/phpcs-rules/issues/131)) and Interface
  Segregation ([#132](https://github.com/mike-bronner/phpcs-rules/issues/132))
  each have a focused sniff issue. An open issue is a plan, not enforcement;
  until each ships, those two principles rest entirely on code review.
- **One candidate was rejected** — the specific Dependency Inversion heuristic
  of flagging a concrete type hint. It is rejected for named token-level facts,
  not a general appeal to semantics, and a different DIP slice is shipped in its
  place.

## Partial enforcement assessment

Each principle below names **one concrete heuristic that was actually
evaluated**, the **PHPCS token or construct** the evaluation turned on, and the
resulting accept or reject call. The bar is the same in both directions: a
heuristic is accepted when a single file's tokens carry everything the check
needs, and rejected only when a specific token-level fact makes the check
impossible or worthless — not because the principle "sounds semantic".

Outcome summary:

| Principle | Candidate heuristic evaluated | Call | Where it lives |
|---|---|---|---|
| Single Responsibility | class size and coupling metrics | **accepted** | 7 shipped sniffs (#80, #83, #87, #93, #96, #98, #114) |
| Open-Closed | `switch`/`if`-`elseif` dispatch on one type discriminator | **accepted** | shipped sniff `CleanCode.Conditionals.TypeDiscriminatorDispatch` ([#324](https://github.com/mike-bronner/phpcs-rules/issues/324)) |
| Liskov Substitution | method body that is a single `throw` in a subtype | **accepted** | [#131](https://github.com/mike-bronner/phpcs-rules/issues/131) |
| Interface Segregation | `interface` declaring more than N method signatures | **accepted** | [#132](https://github.com/mike-bronner/phpcs-rules/issues/132) |
| Dependency Inversion | type hint naming a `final`/concrete class | **rejected** (a *different* DIP slice is shipped) | [#72](https://github.com/mike-bronner/phpcs-rules/issues/72) / [#176](https://github.com/mike-bronner/phpcs-rules/issues/176) |

### Single Responsibility — accepted, already shipped

**Heuristic evaluated:** a class carrying too many methods, fields, public
members, lines, branches, or collaborators is doing more than one job, so class
size and coupling metrics are the token-visible proxy for "more than one reason
to change".

**Construct checked:** `T_CLASS` and its `scope_opener`/`scope_closer` range.
Every input the metrics need — the `T_FUNCTION` declarations a class scope holds
directly, the `T_VARIABLE` property declarations, the visibility modifiers, the
line span between the scope braces, the branch tokens, and the distinct type
names a class references — sits inside that one scope. Nothing crosses a file
boundary, so the check is decidable from tokens alone.

**Call: accepted.** No new issue is opened, because every one of these is
already implemented and wired into the master `rules.xml` through its
`./CleanCode/ruleset.xml` reference. Each was verified closed as *completed*
(not wontfix) with its sniff present in the package:

| Proxy | Issue | Shipped sniff | Maps to |
|---|---|---|---|
| TooManyMethods | [#80](https://github.com/mike-bronner/phpcs-rules/issues/80) | `CleanCode.CodeSize.TooManyMethods` | size |
| TooManyPublicMethods | [#83](https://github.com/mike-bronner/phpcs-rules/issues/83) | `CleanCode.Classes.TooManyPublicMethods` | size |
| ExcessiveClassComplexity | [#87](https://github.com/mike-bronner/phpcs-rules/issues/87) | `CleanCode.Metrics.ExcessiveClassComplexity` | complexity |
| ExcessiveClassLength | [#93](https://github.com/mike-bronner/phpcs-rules/issues/93) | `CleanCode.Classes.ExcessiveClassLength` | size |
| ExcessivePublicCount | [#96](https://github.com/mike-bronner/phpcs-rules/issues/96) | `CleanCode.Metrics.ExcessivePublicCount` | size |
| TooManyFields | [#98](https://github.com/mike-bronner/phpcs-rules/issues/98) | `CleanCode.Metrics.TooManyFields` | size |
| CouplingBetweenObjects | [#114](https://github.com/mike-bronner/phpcs-rules/issues/114) | `CleanCode.Metrics.CouplingBetweenObjects` | coupling |

Every issue in that table is closed as completed and scope-matches a size or
coupling proxy; none is closed-wontfix and none was cited without its sniff
being found in the package. Re-declaring them under an SRP banner would add a
duplicate rule, not a new check.

**What the proxy does not cover:** a small, tightly-focused class can still hold
two responsibilities, and a large one can hold exactly one. The metrics point at
candidates; the reason-to-change judgement stays with review.

### Open-Closed — accepted, already shipped

**Heuristic evaluated:** a `switch` statement, or an `if`/`elseif` chain, that
dispatches on the **same type-discriminator read** across three or more literal
branches. Adding a new variant of that type forces an edit to this construct —
which is the definition of closed for extension and open for modification.

**Construct checked:**

- `T_SWITCH` carries `parenthesis_opener`/`parenthesis_closer` around its
  subject and `scope_opener`/`scope_closer` around its arms, and each arm is a
  `T_CASE`/`T_DEFAULT` with its own scope. Counting arms and reading the subject
  is a scope-map walk inside one file.
- The `if` form is the same walk over `T_IF`/`T_ELSEIF` linked by
  `scope_condition`, in every spelling PHPCS attaches scope to (merged `elseif`,
  spaced `else if`, brace-less, and `if:`/`endif`).
- The discriminator itself is a comparable token sequence: `T_VARIABLE` +
  `T_OBJECT_OPERATOR` + `T_STRING` for `$shape->type`, or `T_VARIABLE` +
  `T_OPEN_SQUARE_BRACKET` + `T_CONSTANT_ENCAPSED_STRING` for `$row['type']`.
  "The same discriminator in every branch" is a textual comparison of those
  sequences — no resolution of what the variable holds is required.

**Call: accepted.** Every input is in one file, and the shape is exactly the
OCP smell rather than a generic complexity signal.

**Why an existing sniff does not already cover it.** Two shipped sniffs are
adjacent and neither claims this slice:

- `CleanCode.Conditionals.AvoidConditionals`
  ([#12](https://github.com/mike-bronner/phpcs-rules/issues/12)) reports *every*
  `switch` and *every* `if`/`elseif` once, as an undifferentiated complexity
  count. It never asks what the branches dispatch on, so it cannot separate a
  type dispatch from any other conditional.
- `CleanCode.Conditionals.MappingArrayCandidate`
  ([#163](https://github.com/mike-bronner/phpcs-rules/issues/163)) is the closer
  match, and it excludes this shape twice over by design: it registers on `if`
  alone, deliberately skipping `switch` and `match`, and it excludes any subject
  that is not a plain variable — `$this->status` and `$row['type']` are named in
  its own exclusion table, and those property and index reads are precisely the
  discriminator this heuristic keys on.

The slice was therefore real and unclaimed, and it now ships as the sniff below
([#324](https://github.com/mike-bronner/phpcs-rules/issues/324)). The overlap
with `AvoidConditionals` is **deliberate**: that sniff counts a branch, this one
names a pattern, and `MappingArrayCandidate` set the precedent for an overlap of
that kind.

**What the heuristic does not cover:** whether a given dispatch *should* have
been polymorphism is a design call — some discriminator switches sit at a
serialization boundary where polymorphism has nowhere to attach. The sniff is a
warning-level prompt, and the judgement stays with review.

#### The sniffed slice

Spun out of this standard
([#5](https://github.com/mike-bronner/phpcs-rules/issues/5)) and scoped by
[#324](https://github.com/mike-bronner/phpcs-rules/issues/324),
`CleanCode.Conditionals.TypeDiscriminatorDispatch` warns on a `switch`, or an
`if`/`elseif` chain, that dispatches on one type-discriminator read across three
or more literal branches.

```php
// The shape: a new shape type means editing this method.
switch ($shape->type) {
    case 'circle': return M_PI * $shape->radius ** 2;
    case 'square': return $shape->side ** 2;
    case 'rect':   return $shape->width * $shape->height;
}
```

| | |
|---|---|
| Registers on | `T_SWITCH`, `T_IF` |
| Codes | `.SwitchDispatch` at the `switch` keyword, `.IfChain` at the leading `if` — one warning per construct, never one per arm |
| Severity | **warning**, not error — a dispatch at a serialization boundary has nowhere to attach polymorphism |
| Fixer | none. The remedy is a type hierarchy or a map plus every call site rewritten, which is a design change rather than a mechanical one |
| Property | `minimumBranches`, default `3` |

A construct qualifies only when the subject is a **discriminator read** — a
variable plus exactly one property or index hop (`$shape->type`,
`$shape?->type`, `$row['type']`) — and every branch dispatches on that same
read, compared token for token including the base variable's own name, so
`$shape->type` and `$model->type` are never one chain. For the `if` form each
condition must be `<discriminator> === <literal>` or the literal-first mirror of
it (`==` too); for `switch`, every `case` label must itself be a scalar literal,
and one class constant or bare expression as a label disqualifies the whole
switch. Toward `minimumBranches`, each `case` label counts on its own — stacked
labels sharing one fallthrough body count once each — `default` counts as one
wherever it sits, and a trailing `else` counts as one. Only branches of the same
construct are counted: two `if` statements written back to back are two
constructs, however alike they read, and their branches are never added up. A
chain also stops where PHP stops it — a clause whose brace-less body writes an
`if` of its own hands every following `elseif` and `else` to that nested `if`,
which is the binding PHP itself uses, so those branches belong to the nested
chain rather than to the outer one.

Never flagged: a plain-variable subject (`switch ($type)`, `if ($type ===
'circle')`, which `MappingArrayCandidate` already owns in its `if` form), any
non-literal or compound branch condition (`instanceof`, ranges, `!==`, calls,
`&&`/`||`), `switch (true)`, a read more than one hop deep
(`$row['meta']['type']`, `$a->b->type`), and `match` — the sniff never registers
on it, because `match` is the construct `MappingArrayCandidate` and
`AvoidConditionals` both recommend as the *replacement*, and flagging it would
have the ruleset argue with itself.

Raise the threshold from a consuming ruleset where three branches read as
ordinary:

```xml
<rule ref="CleanCode.Conditionals.TypeDiscriminatorDispatch">
    <properties>
        <property name="minimumBranches" value="5"/>
    </properties>
</rule>
```

### Liskov Substitution — accepted, focused sniff issue open

**Heuristic evaluated:** a method whose entire body is a single `throw`
statement, declared in a class that `extends` a parent or `implements` an
interface — *refused bequest*, the subtype rejecting behaviour its supertype
promises. Callers substituting the subtype break, which is what LSP forbids.

**Construct checked:** `T_FUNCTION` with its `scope_opener`/`scope_closer`; the
body qualifies when its first non-whitespace, non-comment token is `T_THROW` and
that statement's `T_SEMICOLON` is the last token before the closer. The
hierarchy test is `File::findExtendedClassName()` and
`File::findImplementedInterfaceNames()`, both of which read the `T_EXTENDS` and
`T_IMPLEMENTS` tokens of the declaration in this same file. The parent's own
source is never needed: the signal is the stub, not what it overrides.

**Call: accepted.** Tracked as
[#131](https://github.com/mike-bronner/phpcs-rules/issues/131).

**What the heuristic does not cover:** true substitutability is behavioural — a
subtype that narrows a precondition or widens a postcondition breaks LSP while
implementing every method fully. Only the stub-out shape is token-visible.

### Interface Segregation — accepted, focused sniff issue open

**Heuristic evaluated:** an `interface` declaring more than a configurable
number of method signatures is a countable fat-interface signal; the wider the
surface, the likelier some signatures do not apply to every implementer.

**Construct checked:** `T_INTERFACE` with its `scope_opener`/`scope_closer`, and
a count of the `T_FUNCTION` declarations that scope holds directly. Both are
single-file reads. This is genuinely uncovered rather than a restatement of the
SRP metrics above: `CleanCode.CodeSize.TooManyMethods` registers on `T_CLASS`
only — PHPCS gives interfaces their own `T_INTERFACE` token — so no shipped
method-count sniff ever sees an interface.

**Call: accepted.** Tracked as
[#132](https://github.com/mike-bronner/phpcs-rules/issues/132).

**What the heuristic does not cover:** a wide interface every implementer fully
honours is not an ISP violation, and a two-method interface can violate it if
one method is dead weight for half its implementers. The count is a prompt.

### Dependency Inversion — candidate rejected, a different slice already shipped

**Heuristic evaluated:** flagging a constructor parameter or promoted property
whose type hint names a `final class`, or any other concrete (non-interface)
type, as depending on a concretion instead of an abstraction.

**Construct checked:** `File::getMethodParameters()` on the `T_FUNCTION` named
`__construct`, which returns each parameter's `type_hint` together with its
modifiers; for a plain property, the type sits between the visibility modifier
and the `T_VARIABLE`. Reading the hint is not the problem — PHPCS hands over the
name as `T_STRING`/`T_NS_SEPARATOR` tokens, with `T_NULLABLE`, `T_TYPE_UNION`,
and `T_TYPE_INTERSECTION` marking the shape.

**Call: rejected**, for two specific token-level facts rather than a semantic
hand-wave:

1. **`final` is not present at the use site.** `T_FINAL` is a modifier on a
   `T_CLASS` *declaration*. A type hint is a bare name, so the tokens the sniff
   reads carry no `final` marker at all — the "type hint naming a `final class`"
   half of the candidate has nothing to match on in the file being linted.
2. **The kind behind the name is in another file.** Deciding
   interface-versus-class means resolving `Mailer` to a `T_INTERFACE` or a
   `T_CLASS` declaration. A `use App\Contracts\Mailer;` import supplies a
   namespace path, not a kind, and PHPCS has no symbol table, no autoloader, and
   no cross-file index — it hands a sniff one file's token stream. This is the
   same limitation already recorded on
   [#10](https://github.com/mike-bronner/phpcs-rules/issues/10) and in
   [Dependency Injection](dependency-injection.md), reached independently here.

The symmetric check — could this have landed on *lintable*? There is a variant
that is fully decidable from one file, so the rejection rests on yield rather
than on decidability, and it is worth being explicit about both halves:

- **A hint naming a class-like declared in that same file** needs no resolution
  at all — the `T_CLASS` or `T_INTERFACE` declaration is right there in the
  token stream. But `CleanCode.Files.NoProceduralCode`
  ([#129](https://github.com/mike-bronner/phpcs-rules/issues/129)) already makes
  any top-level statement outside a *single* class-like declaration an error
  under `src/` and `app/`, so a file holding both the hinted type and the class
  that hints it is already a violation of another shipped rule. The population
  this variant could report on is empty by construction.
- **The `T_SELF`/`T_STATIC`/`T_PARENT` keywords** are unambiguously concrete
  with no resolution needed, so a sniff could certainly flag them. It should
  not: a constructor parameter hinted `self` is a copy or merge constructor,
  and one hinted `parent` names the class's own supertype. Depending on your
  own type is not depending on a concretion *instead of* an abstraction —
  there is no abstraction to invert toward — so every report would be a false
  positive by construction.

**A different DIP slice is already enforced, so this principle is not
"none found".** Depending on a concretion has a second token-visible form that
needs no name resolution at all: *building* the collaborator instead of
receiving it. `CleanCode.Classes.DisallowConstructorInstantiation` warns once
per `new` inside the body of a `__construct` that a class-like scope holds,
detection-only, and is shipped and wired into `rules.xml` through the
`./CleanCode/ruleset.xml` reference. It is tracked under
[Dependency Injection #72](https://github.com/mike-bronner/phpcs-rules/issues/72)
— closed as completed — with its scoping recorded on
[#176](https://github.com/mike-bronner/phpcs-rules/issues/176), and documented
in [Dependency Injection](dependency-injection.md). DIP and Dependency Injection
are the same dependency-abstraction thread seen from two sides, so this
assessment defers to #72 rather than opening a competing issue: no new sniff
issue is opened for DIP, and any future work on the injection heuristic belongs
on #72's thread.

**What stays with review:** whether a given collaborator warrants an abstraction
at all, and whether a suitable contract exists to depend on. Those need the
application's wiring, which no single-file scan sees.

## What remains code review

The semantic core of all five principles. The rules above make a handful of
structural symptoms visible and countable — class size, coupling, a stubbed-out
override, a wide interface, a type dispatch, a hard-wired constructor. Whether
any one of them is a genuine SOLID violation, and what the right design change
is, is a judgement about intent and about relationships that span files. Every
one of these rules is detection-only for that reason — none ships a fixer,
because every remedy is a design change rather than a mechanical rewrite. Each
report is a prompt for the review conversation, not a verdict. (Severity varies
by rule: the shipped size and coupling sniffs keep whatever severity their
PHPMD-parity issue set, several of them error-level, while
`DisallowConstructorInstantiation`, `TypeDiscriminatorDispatch` and the two
proposed sniffs are warning-level.)

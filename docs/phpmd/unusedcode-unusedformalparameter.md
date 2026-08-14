# PHPMD/UnusedCode: UnusedFormalParameter

## Standard

- A declared parameter must be used in the body that declares it.

**Why:**

- A parameter nothing reads is dead weight in the signature: every caller still
  has to supply it, and every reader still has to work out what it is for.
- It is usually the residue of a refactor — the body stopped using the value and
  the signature was never trimmed — so it also hides how much a method really
  needs.

PHPMD flags this as `UnusedFormalParameter` in its Unused Code ruleset. It has
no configurable thresholds.

```php
private function bar($howdy) {
    // $howdy is not used
}
```

_Source: [phpmd.org/rules/unusedcode.html](https://phpmd.org/rules/unusedcode.html)_

## Enforceability — Tier 2 (custom sniff)

The custom sniff `CleanCode.DeadCode.UnusedFormalParameter` carries this rule,
wired into the master `rules.xml`
([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)).

- **Detection** — a parameter no statement in the body reads is flagged at the
  parameter's own line and column, in functions, methods, constructors,
  closures, and arrow functions. A read counts whether it is plain (`$name`),
  inside an interpolated string or heredoc, or inside a nested closure or arrow
  function.
- **Not auto-fixable** — deleting a parameter changes the signature and breaks
  every caller, so there is nothing safe for `phpcbf` to write. This matches
  PHPMD, which reports rather than rewrites.
- **Reported as an error, not a warning** — a warning leaves `phpcs` exiting 0
  on an unused parameter, which would mean `phpmd` still had to run for this
  rule: the one thing this issue exists to stop.
- **No properties** — PHPMD's rule has no thresholds, so there is nothing to
  transfer and nothing to drift.

### Why a custom sniff, and not a wiring

Both candidate wirings were built, measured against a live PHPMD 2.15.0, and
rejected. Each stays **silent on shapes PHPMD reports**, which is the one
direction that puts `phpmd` back in the pipeline:

| Candidate | Why it was rejected |
|---|---|
| `Generic.CodeAnalysis.UnusedFunctionParameter` | It never changes its *verdict* for an inherited signature — it changes the error *code*, to a `FoundInExtendedClass…` or `FoundInImplementedInterface…` variant, whenever the enclosing class extends or implements anything. Excluding those six codes to buy PHPMD's override exemption also silences the class's own **non-inherited** methods. It additionally exempts an empty or comment-only body and `__unserialize()`, both of which PHPMD reports. |
| `SlevomatCodingStandard.Functions.UnusedParameter` | It has no inherited-signature exemption at all, so it reports **every** override — the false positive [#120's acceptance criteria](https://github.com/mike-bronner/phpcs-rules/issues/120) explicitly forbid. It was wired here for the No Dead Code standard ([#29](https://github.com/mike-bronner/phpcs-rules/issues/29)) and is replaced by this sniff, which is a strict superset of it apart from the two annotations below. |

### How the inherited-signature exemption is decided

This is the whole difficulty of the rule, and it is worth stating exactly.

PHPMD exempts a parameter that only exists to satisfy an inherited signature,
and resolves that through PDepend's **whole-project type map**:
`PHPMD\Node\MethodNode::isDeclaration()` asks PDepend for the parent class and
each interface, then looks the method name up in `getAllMethods()`. A PHPCS
sniff sees one file at a time and has no such map.

The exemption is genuinely cross-file, measured on PHPMD 2.15.0:

| Shape | PHPMD |
|---|---|
| Parent and child in the same file, method overridden | silent |
| Parent and child in two files, both analyzed | silent |
| Child analyzed alone, parent outside the analyzed set | **flags** |

Three signals stand in for that map, and between them they cover every override
an author can actually declare:

- **A same-file resolvable override.** When the parent class or interface is
  declared in the same file, the lookup PHPMD does is available and is done —
  transitively, and including methods a resolved parent draws from a trait,
  because PDepend's `getAllMethods()` includes those too. The class's *own*
  traits are deliberately not consulted: PHP gives a class's own method
  precedence over a trait's, and PHPMD asks only about the parent chain.
- **`@inheritdoc`.** PHPMD honours it on its own
  (`Rule/UnusedFormalParameter.php::isInheritedSignature()`), in the bare,
  mixed-case, and `{@inheritdoc}` spellings. Confirmed against a live run.
- **`#[\Override]`.** PHPMD does *not* honour this — it reports a parameter
  under an `#[\Override]` whose parent it cannot see. Honouring it loses no
  coverage on code that runs, because PHP 8.3 itself rejects the attribute at
  compile time unless the method genuinely overrides something. The attribute is
  a compiler-checked proof of the very fact PHPMD needs a type map to establish.

What is left over is the deliberate cost this rule accepted: **an override of a
parent this file cannot see, carrying neither annotation, is reported here and
not by a whole-project PHPMD run.** Annotating it is the fix, and the annotation
is worth having on its own.

### Where the sniff and PHPMD differ

Rather than weaken a fixture to force agreement, the differences are recorded
here and pinned by `tests/fixtures/UnusedFormalParameterSniff/divergences.php`.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| Unused parameter in a plain function or an inheritance-free class | flags | flags |
| Unused parameter named only in a docblock | flags | flags |
| Empty or comment-only body | flags | flags |
| `__unserialize()` | flags | flags |
| `__invoke()` | flags | flags |
| Non-override method in a class that extends or implements | flags | flags |
| Variadic, by-reference, or defaulted parameter, unread | flags | flags |
| Static, trait, and enum methods | flags | flags |
| Parameter reachable only through `func_num_args()` | flags | flags |
| Bodyless interface or `abstract` declaration | silent | silent |
| Unused promoted constructor property | silent | silent |
| Magic method with a PHP-fixed signature | silent | silent |
| Method carrying `@inheritdoc` | silent | silent |
| Genuine override of a parent resolvable in the same file | silent | silent |
| Parameter reachable only through `func_get_args()` | silent | silent |
| Parameter named by `compact('name')` | silent | silent |
| Dynamic read — `${'name'}` | flags | flags |
| Closure parameter | silent | **flags** |
| Arrow-function parameter | silent | **flags** |
| Method of an anonymous class | silent | **flags** |
| Override of a parent in another file, unannotated | silent | **flags** |
| `#[\Override]` on a method that overrides nothing | flags | **silent** |

Reading the rows that disagree:

- **Closures, arrow functions, and anonymous-class methods.** PHPMD's rule is
  `FunctionAware` and `MethodAware` only, so PDepend never hands it any of the
  three. The extra reports are kept: each is the same defect in a construct
  PHPMD cannot see, and suppressing a true defect to match a gap is not parity
  worth having. `CleanCode.Functions.ExcessiveParameterList` makes the same call
  on the same constructs, so the two sniffs stay consistent.
- **An unannotated override of a parent in another file.** The cost set out
  above. Add `@inheritdoc` or `#[\Override]`.
- **`#[\Override]` on a method that overrides nothing.** Only reachable in code
  PHP itself refuses to compile, so no coverage is lost on anything that runs.

Two boundaries worth naming, because both were assumed wrong before being
measured:

- **`func_get_args()` exempts the whole signature; `func_num_args()` exempts
  nothing.** PHPMD reports through `func_num_args()`. `func_get_args()` exempts
  even when it is called from inside a nested closure.
- **`compact()` exempts only the parameter it names**, not its siblings.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/unusedcode.xml/UnusedFormalParameter`, and
`phpcs --standard=rules.xml`. On `failing.php` the two reports are identical:
seventeen findings, same lines, same parameters.

Behaviour tests covering compliant code, per-line and per-column violation
reporting, the message wording, the error severity, the absence of a fixer, and
every divergence above live at
`tests/Standards/UnusedFormalParameterTest.php`. The sniff is also in the
generic three-fixture sweep in `tests/Contract/SniffContractTest.php`, and its
role in the No Dead Code standard is exercised end-to-end through the phpcs CLI
by `tests/Ruleset/NoDeadCodeRulesetTest.php`.

Its fixtures follow the contract CONTRIBUTING.md prescribes, under
`tests/fixtures/UnusedFormalParameterSniff/`: `passing.php` for code the rule
must stay silent on — carrying one instance of every exemption, in the spelling
that exercises it — and `failing.php` for the parity set, plus `divergences.php`
for the shapes that belong to neither. There is no `autofixed.php`, because the
rule is not fixable: a test runs the real fixer over `failing.php` and asserts
its output is byte-identical to the input, so "unfixable" is measured rather
than assumed.

## What remains code review

**A parameter kept only for a signature no linter can see** — a framework
callback, a queue handler, an event listener, an override of a vendor base
class. Where the signature comes from a parent, `#[\Override]` or `@inheritdoc`
states that in a way both this sniff and PHPMD understand. Where it comes from a
convention no type carries — a hook name, a dispatcher's argument list —
suppress it deliberately with `phpcs:ignore` rather than by relaxing the rule.

**A dynamic read** — `${'name'}`. Neither tool resolves it, and both report the
parameter as unused.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.

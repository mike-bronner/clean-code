# PHPMD CodeSize: ExcessiveParameterList

## Rule

- A function or method must declare fewer than `minimum` parameters (default
  **10**).

**Why:** a long parameter list says the callee is being handed a bag of loose
values that belong together. Group the related parameters into an object and
pass that instead.

```php
// PHPMD (and this ruleset) flags this:
class Foo {
    public function addData(
        $p0, $p1, $p2, $p3, $p4,
        $p5, $p6, $p7, $p8, $p9) {
    }
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html#excessiveparameterlist)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/ExcessiveParameterList` | `CleanCode.Functions.ExcessiveParameterList` (message code `.Found`) |

Enforced by the custom `CleanCode.Functions.ExcessiveParameterList` sniff,
registered automatically from `CleanCode/Sniffs/` when the standard loads —
[#95](https://github.com/mike-bronner/phpcs-rules/issues/95). Running `phpcs`
with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not
have to run separately for it.

No existing PHPCS, Slevomat, or PHPCSUtils sniff counts *declared* parameters.
`Generic.Metrics.CyclomaticComplexity` and `Generic.Metrics.NestingLevel` are
the only bundled metric sniffs, and neither looks at a parameter list;
`PHPCSUtils\Utils\PassedParameters` counts the arguments at a *call site*, not
the parameters in a declaration. Hence a custom sniff.

- **Detection** — the sniff registers on `T_FUNCTION` and counts the entries
  `getMethodParameters()` returns for the declaration, reporting on the line of
  the `function` keyword. That is the line PHPMD reports too, including for a
  signature spread over several lines.
- **The threshold is inclusive** — see the section below; this is the one place
  where phpmd.org's prose and the tool disagree.
- **Severity** — the sniff raises errors, so a violation fails a `phpcs` run
  the way it fails a `phpmd` run. No `<type>` override is needed, unlike
  `Squiz.PHP.Eval` or `VariableAnalysis`.
- **Not auto-fixable** — matching PHPMD. Collapsing a parameter list into an
  object means introducing that object and rewriting every call site, which is
  a design change with no mechanical rewrite.

## The threshold is inclusive — the documented default is 10, and 10 violates

PHPMD's rule class is `PHPMD\Rule\Design\LongParameterList`, and its whole body
is:

```php
$threshold = $this->getIntProperty('minimum');
$count = $node->getParameterCount();
if ($count < $threshold) {
    return;
}
$this->addViolation(...);
```

The guard returns on `$count < $threshold`, so a declaration with **exactly**
`minimum` parameters is reported. With the shipped default of 10, ten
parameters violate and nine do not.

This contradicts the way the rule is usually read. phpmd.org's message —
"Consider reducing the number of parameters to less than 10" — and
[#95](https://github.com/mike-bronner/phpcs-rules/issues/95)'s acceptance
criteria, which paraphrase it as "≤10 declared parameters → no violation", both
describe a strict `>`. A live PHPMD 2.15.0 run over
`tests/fixtures/ExcessiveParameterListSniff/boundaries.php` reports the
ten-parameter method, confirming the code rather than the prose.

The sniff follows the tool. Matching the prose instead would drop a report
PHPMD makes, and a rule that reports *less* than PHPMD is the one direction
that puts `phpmd` back in the pipeline for it.

## Configuration

The property is spelled `minimum`, exactly as PHPMD spells it, and carries
PHPMD's own shipped default, so an existing PHPMD configuration transfers
verbatim:

```xml
<rule ref="CleanCode.Functions.ExcessiveParameterList">
    <properties>
        <property name="minimum" value="5"/>
    </properties>
</rule>
```

`CleanCode/ruleset.xml` sets no property, leaving the default of 10 in force.

A `minimum` that is not a positive whole number — `value="ten"`, `value="0"`,
`value="-3"`, `value="7.5"`, or an empty `value=""` — falls back to 10 rather
than being used as written. A zero or negative threshold would otherwise report
every declaration in a codebase, including one taking no parameters at all.
This also keeps a configuration typo from aborting the run: PHPCS hands a
sniff's property whatever the XML holds, without casting, so a threshold
property carrying a native `int` type dies with an uncaught `TypeError` on both
`value="ten"` and `value=""`.

## Shapes that are counted

Confirmed against a live PHPMD 2.15.0 run over
`tests/fixtures/ExcessiveParameterListSniff/`, which reports the same lines
with the same "method" / "function" wording:

- **Methods of classes, traits, enums, and interfaces**, and **plain
  functions**, including a named function nested inside another function.
  PHPMD's rule implements `FunctionAware` and `MethodAware`, which is exactly
  these.
- **Declarations with no body** — an `abstract` method, an interface method —
  counted from the signature like any other.
- **Promoted constructor properties**. Promotion does not stop them being
  parameters.
- **A variadic parameter counts once.** `($a1, …, $a9, ...$rest)` is ten.

## Shapes that are not counted

- **Closures and arrow functions**, however many parameters they declare.
  PHPMD's rule is `FunctionAware` and `MethodAware` only, so it never visits
  either; registering on `T_FUNCTION` alone reproduces that exactly, since
  `T_CLOSURE` and `T_FN` are separate tokens.
- **Arguments at a call site.** The rule counts what a declaration declares.

## Divergence from PHPMD — one shape, in the safe direction

A method of an **anonymous class** is reported here and is *not* reported by
PHPMD, confirmed silent under a live run over
`tests/fixtures/ExcessiveParameterListSniff/divergences.php`. PDepend never
surfaces an anonymous class's methods to a `MethodAware` rule, so PHPMD cannot
see one.

That is a gap in PHPMD rather than a decision about the rule, and reproducing
it would mean writing code whose only purpose is to suppress a real defect. The
extra report is kept, which leaves this sniff a strict superset of PHPMD: every
declaration PHPMD flags is flagged here, plus this one. No PHPMD finding is
lost, which is what keeps `phpmd` out of the pipeline for this rule.

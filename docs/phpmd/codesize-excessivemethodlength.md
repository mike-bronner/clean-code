# PHPMD CodeSize: ExcessiveMethodLength

## Rule

- A method or function does not reach a configured number of lines.

**Why:** a method that long is nearly always doing several jobs at once, and
very often the residue of copy-and-paste. Extract helpers until each one states
a single intent.

```php
// PHPMD (and this ruleset) flags this:
class Foo {
    public function doSomething() {
        print("Hello world!" . PHP_EOL);
        print("Hello world!" . PHP_EOL);
        // 98 copies omitted for brevity.
    }
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/ExcessiveMethodLength` | `CleanCode.Functions.ExcessiveMethodLength` (message code `.Found`) |

No existing PHPCS or Slevomat sniff expresses this rule. PHPCS's own
`Generic.Metrics.*` pair measures cyclomatic complexity and nesting depth rather
than length, and `Generic.Files.LineLength` measures how wide a line is, not how
many there are.

The one real candidate is `SlevomatCodingStandard.Functions.FunctionLength`. It
was configured as close to PHPMD as it goes — `maxLinesLength` 100,
`includeComments` and `includeWhitespace` both true — and run against
`tests/fixtures/ExcessiveMethodLengthSniff/failing.php`, where it reported
**nothing**, on a fixture PHPMD reports every one of its four declarations on.
Three independent differences produce that, and no combination of properties
closes any of them:

- **A different span.** `FunctionHelper::getLineCount()` counts from the body's
  opening brace to the token before the closing one, so the four declarations
  measure 97, 95, 97, and 97 where PDepend's `loc` measures 100 apiece. The
  signature and the closing brace are outside its span and inside PHPMD's.
- **A different comparison.** Slevomat reports on `length > max`; PHPMD reports
  on `loc >= minimum`.
- **A different anchor.** On the modifier-split declaration Slevomat reports
  line 117, the `function` keyword. PHPMD reports line 115, the `public` that
  begins PDepend's node.

So the rule is the custom `CleanCode.Functions.ExcessiveMethodLength` sniff,
wired into the master ruleset (`rules.xml`) through `CleanCode/ruleset.xml`
([#91](https://github.com/mike-bronner/phpcs-rules/issues/91)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

## The metric

PHPMD implements the rule in `PHPMD\Rule\Design\LongMethod`, which reads a line
count PDepend already computed:

```php
$loc = -1;
if ($this->getBooleanProperty('ignore-whitespace')) {
    $loc = $node->getMetric('eloc');
}
if (-1 === $loc) {
    $loc = $node->getMetric('loc');
}
if ($loc < $threshold) {
    return;
}
```

Two things follow, and the sniff reproduces both.

- **The threshold is inclusive.** The early return is on `<`, so a declaration
  of exactly `minimum` lines is already a violation. This is worth stating
  plainly because it is easy to assume the opposite: a 100-line method is
  reported at the default threshold of 100, and only 99 lines is silent.
- **`ignore-whitespace` swaps the metric rather than adjusting it.** It does not
  subtract blank lines from `loc`; it uses `eloc` instead, which drops comment
  lines and usually the signature as well. The name understates it, in PHPMD as
  here.

The two metrics come from PDepend's `NodeLocAnalyzer::visitMethod()`:

| Metric | Definition | Counts |
| --- | --- | --- |
| `loc` | `getEndLine() - getStartLine() + 1` | the whole declaration, blank lines and comments included |
| `eloc` | distinct lines from the body's `{` to its `}` carrying a non-comment token | body only; blank and comment-only lines drop out, and so does the signature whenever the brace is on its own line |

Both exclude the docblock and any attributes above the declaration, because
those sit outside PDepend's node. Both *include* the modifiers: the node starts
at `public`, `final`, `static` and so on, so a modifier written on its own line
lengthens the measured span and is where the violation is reported. A comment
written *between* two modifiers does not move that start: PDepend keeps the node
on the first modifier, so `public`, a comment, then `static function` still
measures and reports from `public` (`configured.php` line 70, confirmed against
the live run below).

## Scope

- **Methods and functions.** Despite the rule's name, `LongMethod` implements
  `FunctionAware` as well as `MethodAware`, so a long plain function is reported
  too — as `The function …()`, matching PHPMD's own wording.
- **Not closures, and not arrow functions.** PDepend models neither as a
  declaration. A closure written inside a method is already part of that
  method's span and lengthens it; one written at file scope is a PHPMD blind
  spot, which this sniff reproduces rather than closing, because reporting it
  would mean reporting code `phpmd` accepts.
- **Bodiless declarations.** An abstract or interface method measures its
  signature under `loc` and zero under `eloc`, as PDepend scores it.

## Configuration

Both properties carry PHPMD's defaults, so `rules.xml` configures neither and an
unconfigured `phpcs` run reports what an unconfigured `phpmd` run reports.

| PHPMD property | Sniff property | Default |
| --- | --- | --- |
| `minimum` | `minimum` | `100` |
| `ignore-whitespace` | `ignoreWhitespace` | `false` |

A project that already tunes PHPMD's rule transfers its values verbatim; only
the spelling of the second changes, because PHPCS sets a PHP property rather
than an XML attribute:

```xml
<rule ref="CleanCode.Functions.ExcessiveMethodLength">
    <properties>
        <property name="minimum" value="60"/>
        <property name="ignoreWhitespace" value="true"/>
    </properties>
</rule>
```

A `minimum` that is not a positive number — a typo, or a deliberate `0` — falls
back to 100 rather than to zero. A threshold of zero reports every declaration
in the codebase, and a standard that fails everything is a standard that gets
switched off.

- **Error severity** — the sniff reports errors, so an over-long method fails a
  `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed in
  `rules.xml`.
- **Not auto-fixable** — matching PHPMD. Shortening a method means extracting
  helpers and naming them, which is a design decision with no mechanical
  rewrite.

## Verification

Parity is not asserted from reading PHPMD's source alone. Every fixture in
`tests/fixtures/ExcessiveMethodLengthSniff/` was run through a live PHPMD 2.15.0
install at `minimum=1` — which makes PHPMD print its measured count for every
declaration it can see — under both settings of `ignore-whitespace`, and the
sniff reproduces all of it: same lines, same counts, same silences.

| Fixture | PHPMD `loc` | PHPMD `eloc` |
| --- | --- | --- |
| `failing.php` lines 14, 115, 216, 318 | 100, 100, 100, 100 | 99, 97, 27, 99 |
| `passing.php` lines 29, 129, 136, 143, 150, 160, 165 | 99, 6, 6, 6, 6, 1, 1 | 98, 5, 5, 5, 5, — , — |
| `configured.php` lines 13, 29, 34, 45, 70 | 15, 4, 9, 14, 7 | 10, 3, 7, 13, 4 |

The two dashes are the bodiless declarations, which score zero and so fall below
even a threshold of 1. The file-scope closure on line 168 of `passing.php` and
the arrow function on line 281 appear in neither column: PHPMD never sees them.

`tests/fixtures/ExcessiveMethodLengthSniff/malformed.php` is the one fixture with
no PHPMD column. It holds a declaration cut short mid-edit, which PDepend cannot
parse at all, so there is no parity to claim — it pins a fail-closed choice of
this ruleset's own: a fragment with neither a body nor a terminating semicolon
measures one line and is never reported, rather than borrowing a semicolon from
the next declaration and being reported as a long method.

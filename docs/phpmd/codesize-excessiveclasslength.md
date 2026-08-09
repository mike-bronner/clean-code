# PHPMD CodeSize: ExcessiveClassLength

## Rule

- A class must be shorter than 1000 lines.

**Why:** a long class file is a sign the class is trying to do too much. Break
it down and reduce it to something manageable.

```php
class Foo {
  public function bar() {
    // 1000 lines of code
  }
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html#excessiveclasslength)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/ExcessiveClassLength` | `CleanCode.Classes.ExcessiveClassLength` (message code `.TooLong`) |

Enforced by the custom `CleanCode.Classes.ExcessiveClassLength` sniff, wired
into the master ruleset (`rules.xml`) with PHPMD's own thresholds
([#93](https://github.com/mike-bronner/phpcs-rules/issues/93)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

### Why no existing sniff fits

`SlevomatCodingStandard.Classes.ClassLength` is the closest thing on offer and
misses on three counts, none of which a property setting reaches:

| | PHPMD | Slevomat `ClassLength` |
| --- | --- | --- |
| What it measures | classes only | every OO scope — interfaces, traits, enums, and anonymous classes too |
| When it reports | length **at least** `minimum` | length **greater than** `maxLinesLength` |
| Where it counts from | the class declaration line | the opening brace |

`Generic.Metrics` carries no class-length sniff at all — it holds
`CyclomaticComplexity` and `NestingLevel` and nothing else. So a custom sniff it
is, and no test was relaxed to make an existing rule fit.

## Behaviour

Everything below was read off PHPMD 2.15.0 (`PHPMD\Rule\Design\LongClass`) and
PDepend 2.16.2 (`PDepend\Metrics\Analyzer\NodeLocAnalyzer`), then confirmed
against live runs of both tools over the fixtures in
`tests/fixtures/ExcessiveClassLengthSniff/`.

- **Classes only.** PHPMD's rule is `ClassAware`, so an interface, a trait, or
  an enum is never reported however long it grows, and an anonymous class is not
  reported in its own right. The sniff registers on `T_CLASS`, which
  PHP_CodeSniffer keeps distinct from `T_INTERFACE`, `T_TRAIT`, `T_ENUM`, and
  `T_ANON_CLASS`, so it inherits the same scope for free.
- **Reported at the declaration start.** PDepend takes a class's start line from
  the first of its `abstract`/`final`/`readonly` modifiers, not from the `class`
  keyword — so `abstract` on its own line above `class Foo` moves the report up
  a line *and* adds one to the length. A doc block or an attribute above the
  declaration is part of neither.
- **The threshold is inclusive.** PHPMD returns early only when the length is
  *below* `minimum`, so a class of exactly 1000 lines is already a violation.
  "Minimum" names the smallest reportable length, not the largest allowed one.
- **Not auto-fixable** — matching PHPMD. Splitting an over-long class is a
  design decision, not a mechanical rewrite.

### Properties

| Property | Default | PHPMD name |
| --- | --- | --- |
| `minimum` | `1000` | `minimum` |
| `ignoreWhitespace` | `false` | `ignore-whitespace` |

`rules.xml` writes both out explicitly rather than leaning on the sniff's own
defaults, so the shipped configuration is the one under test.

**`ignoreWhitespace` is not "the same count without the blank lines."** PHPMD
implements it by swapping PDepend's `loc` metric for its `eloc`, and the two
measure quite different things:

- `loc` — every physical line from the declaration start through the closing
  brace, comments and blank lines included.
- `eloc` — the sum, over the class's *own* methods, of the distinct lines
  carrying a non-comment token from each method's opening brace onwards.
  Constants, properties, method signature lines, and the class braces
  contribute nothing; an abstract method contributes nothing, because it has no
  body; and the body of an anonymous class declared inside a method *is*
  counted, because those lines lie inside the enclosing method's token range.

The `Ledger` fixture in `tests/fixtures/ExcessiveClassLengthSniff/whitespace.php`
makes the gap concrete: 20 lines by `loc`, 6 by `eloc`. Both numbers come from a
live PHPMD run and are pinned in `tests/Standards/ExcessiveClassLengthTest.php`.

## Divergences

Two, both disclosed rather than papered over.

- **A class whose braces PHP_CodeSniffer mis-pairs is measured short.** A
  closure declared inside a curly-brace property fetch in a `foreach` header —
  `foreach ($rows as $order->{(function () use ($payload) { … })()})` — leaves
  the enclosing class's `scope_closer` pointing at the method's closing brace
  instead of the class's own, and truncates the method's scope the same way. On
  `tests/fixtures/ExcessiveClassLengthSniff/tokenizer-limits.php` the sniff
  reports 9 lines where PHPMD reports 10, and 2 executable lines where PHPMD
  reports 5. The class is still reported, at the right line; the error can only
  ever suppress a report near the threshold, never invent one. Re-pairing
  PHP_CodeSniffer's braces inside the sniff would be a far larger risk than the
  gap. Pinned by the fixture rather than claimed away.
- **This ruleset analyses files PDepend refuses.** PDepend's parser aborts on
  constructs PHP itself accepts — `static::create()` at file scope, for one —
  and PHPMD then reports nothing at all for that file, this rule included.
  `phpcs` tokenises the same file and reports normally, so on those files the
  replacement is strictly more available than the tool it replaces.

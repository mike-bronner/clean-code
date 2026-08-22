# Arrays: Convert To Collection

## Standard

- Whenever possible use collections for manipulation.

**Why:**

- Collections provide a large list of optimized manipulation methods.
- By relying on the framework, we decouple from the direct PHP implementation,
  which may change over major versions, while the framework maintains an
  optimized implementation.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The trigger pattern of this standard **is statically lintable**: bare calls to
native array-manipulation functions (`array_map()`, `array_filter()`,
`array_reduce()`) are token-visible — a function-name token followed by an
open parenthesis, not preceded by `->`, `::`, `new`, or `function` — and each
has a direct Collection equivalent (`collect()->map()/filter()/reduce()`).

That slice is the custom **`CleanCode.Arrays.ConvertToCollection`** sniff
([#165](https://github.com/mike-bronner/phpcs-rules/issues/165)). No existing
PHPCS or Slevomat sniff expresses it: `Generic.PHP.ForbiddenFunctions` detects
the same call shape but carries no notion of a replacement to name, and
Slevomat's array rules speak about syntax (short arrays, trailing commas)
rather than about which API does the manipulating.

- **Detection** — a call to a native array function from the configured list is
  flagged as a collection-pipeline candidate, with the Collection equivalent
  named in the message (`array_map()` → `collect()->map()`).
- **Configurable function list** — the flagged functions are the sniff's public
  `arrayFunctions` property so projects can tune coverage; the shipped default
  is `array_map`, `array_filter`, `array_reduce` (the highest-signal trio with
  one-to-one Collection equivalents). Both `<element>` spellings work:

  ```xml
  <rule ref="CleanCode.Arrays.ConvertToCollection">
      <properties>
          <property name="arrayFunctions" type="array">
              <element key="usort" value="sortBy"/>
              <element value="array_reverse"/>
          </property>
      </properties>
  </rule>
  ```

  An `<element>` with no key names the Collection method by dropping the
  function's `array_` prefix (`array_reverse` → `reverse()`). Give the keyed
  spelling wherever the two names diverge. Function names are matched
  case-insensitively, as PHP resolves them, so `ARRAY_REVERSE` configures the
  same function. The comma-separated `value="a,b"` attribute PHPCS deprecated
  in 3.3.0 is parsed into the same two shapes and still works, but PHPCS 4.0
  removes it.
- **Warning severity, not error** — the standard says "whenever possible", so
  justified native usage (hot paths, plain-PHP contexts) is tolerated; the
  sniff points at conversion candidates rather than mandating a fix. It is
  detection-only for the same reason: rewriting a call into a pipeline changes
  the value's type from `array` to `Collection` at every downstream use.
- **Boundaries** — method calls (`$obj->array_map()`), nullsafe calls, static
  calls, `new`, definitions of same-named functions and methods, and namespaced
  functions of the same name (`App\Support\array_map()`) all stay out. A
  fully-qualified `\array_map()` is the global function, so it is flagged.
  `namespace\array_map()` goes by the namespace in force where it is written:
  a different symbol under a declared namespace, and the native function where
  none is declared. The sniff cannot verify that Laravel /
  `illuminate/collections` is available in the scanned project, so a project
  without collections excludes the sniff from its ruleset. This package does
  not: it runs the sniff over its own plain-PHP source and tolerates the
  warnings that raises, which is exactly the context the warning severity
  exists for. What that tolerance covers is reviewed and pinned rather than
  assumed — see below.

## The package's own source

[#286](https://github.com/mike-bronner/phpcs-rules/issues/286) reviewed every
warning this sniff raises against `CleanCode/` and `tests/` one site at a time:
65 warnings in 37 files, 19 in the shipped sniffs and 46 in the test suite.
Every one is a native call kept on purpose. The pinned set has since grown with
the sniffs that landed after that review — 83 warnings in 50 files, 27 in the
shipped sniffs and 56 in the test suite.

The reason is a single package-level fact rather than 83 separate judgements.
This package is a PHP_CodeSniffer standard; `illuminate/collections` is absent
from its `composer.json` by design, and adding it to `require` so a linter could
call `collect()` would put Laravel's collections in every downstream consumer's
install. So `collect()` does not exist here to call — the plain-PHP context the
warning severity exists for.

Each site's own value was still read before it was left native, and
[PR #312](https://github.com/mike-bronner/phpcs-rules/pull/312) records what
consumes it one site at a time for the 65 sites that existed when that review
ran. 63 of those 65 are consumed by something a
`Collection` does not satisfy: a strict `in_array()` haystack, an argument to
another native array function (`implode()`, `array_keys()`, `array_column()`,
`array_sum()`, `array_diff()`), `sort()` by reference, a declared `array`
return, or a strict comparison against an array literal. The other two —
`tests/Standards/AvoidConditionalsTest.php:127` and
`tests/Standards/LogicalGroupingsTest.php:206` — are only counted, which a
`Collection` satisfies through `Countable`; for those two the package-level fact
is the whole reason rather than a reinforcement of the site's own usage.

The 18 sites added since #312 are pinned on the package-level fact alone. They
have not been walked one at a time the way that PR walked the first 65, so the
per-site tally above stays scoped to that set rather than widened to cover
reviews nobody performed.

That reviewed set is pinned by file, line and column in
`tests/Standards/ConvertToCollectionTest.php`
(`CONVERT_TO_COLLECTION_REVIEWED_SITES`), so a native call added, moved or
removed anywhere under `CleanCode/` or `tests/` fails that test. A count alone
would not: one site moving while another disappears leaves it unchanged.
Nothing is suppressed to reach that state — no `phpcs:ignore`, no
`exclude-pattern`, and no ruleset registration for this sniff. `rules.xml`
carries the same account in its "Arrays: Convert To Collection" comment block.

## What remains code review

Whether a particular manipulation is *better* as a collection pipeline is a
judgement about context, not tokens. `foreach` loops that accumulate into
arrays are also collection candidates but are not reliably token-detectable.
Those calls stay with code review.

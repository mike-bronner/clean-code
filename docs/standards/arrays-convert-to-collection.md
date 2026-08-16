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
Its mirror image — the same functions applied to a value that is *already* a
Collection — is [Collections: Only Use Collection
Methods](collections-only-use-collection-methods.md), enforced by
`CleanCode.Collections.OnlyUseCollectionMethods`.

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
  exists for.

## What remains code review

Whether a particular manipulation is *better* as a collection pipeline is a
judgement about context, not tokens. `foreach` loops that accumulate into
arrays are also collection candidates but are not reliably token-detectable.
Those calls stay with code review.

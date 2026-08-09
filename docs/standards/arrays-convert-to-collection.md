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
  one-to-one Collection equivalents). Both PHPCS array-property spellings work:

  ```xml
  <rule ref="CleanCode.Arrays.ConvertToCollection">
      <properties>
          <property name="arrayFunctions" type="array" value="array_map=>map,usort=>sortBy"/>
      </properties>
  </rule>
  ```

  A plain comma-separated list (`value="array_values,array_reverse"`) works too;
  the Collection method is then the function name without its `array_` prefix.
  Give the `name=>method` spelling wherever the two names diverge.
- **Warning severity, not error** — the standard says "whenever possible", so
  justified native usage (hot paths, plain-PHP contexts) is tolerated; the
  sniff points at conversion candidates rather than mandating a fix. It is
  detection-only for the same reason: rewriting a call into a pipeline changes
  the value's type from `array` to `Collection` at every downstream use.
- **Boundaries** — method calls (`$obj->array_map()`), nullsafe calls, static
  calls, `new`, definitions of same-named functions and methods, and namespaced
  functions of the same name (`App\Support\array_map()`) all stay out. A
  fully-qualified `\array_map()` is the global function, so it is flagged. The
  sniff cannot verify that Laravel / `illuminate/collections` is available in
  the scanned project, so projects without collections exclude the sniff from
  their ruleset — this package is one of them, which is exactly the tolerated
  plain-PHP context the warning severity exists for.

## What remains code review

Whether a particular manipulation is *better* as a collection pipeline is a
judgement about context, not tokens. `foreach` loops that accumulate into
arrays are also collection candidates but are not reliably token-detectable.
Those calls stay with code review.

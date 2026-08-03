# Arrays: Array Accessors (`data_get`)

## Standard

- Always use `data_get()` to access arrays, instead of accessing their elements
  directly.

**Why:**

- Provides fallback logic in case the element does not exist in arrays.
- Allows parsing of properties on any kind of object (array, collection,
  object, model) without type checks (reduces mental debt).
- Allows easy parsing of nested objects (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff expresses this rule. The bundled and
Slevomat catalogues police array *syntax* — for example
[`Slevomat Arrays.DisallowImplicitArrayCreation`](https://github.com/slevomat/coding-standard/blob/master/doc/arrays.md)
(assignment into an undeclared array) and `Generic.Arrays.ArrayIndent`
(layout) — not the *accessor* used to read a value, and none of them knows
about `data_get()`, which is a Laravel helper rather than a language feature.
Evaluated against this standard's test suite, they match none of it. The
standard is therefore enforced by the custom `CleanCode.Arrays.ArrayAccessors`
sniff, wired into the master `rules.xml` via the CleanCode standard
([#33](https://github.com/mike-bronner/phpcs-rules/issues/33)).

- **Detection** — a read through a direct accessor is flagged at the variable
  the accessor chain is rooted in. Element reads (`$payload['name']`) are
  reported as `CleanCode.Arrays.ArrayAccessors.DirectArrayAccess`, property
  reads (`$order->reference`, including the nullsafe `$order?->reference`) as
  `CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess`.
- **One diagnostic per chain** — a whole chain collapses into a single
  `data_get()` call, so it earns a single diagnostic:
  `$payload['address']['city']`, `$order->address->city`, and the mixed
  `$payload['items'][0]->name` are each reported once, at their root variable,
  not once per link. Any `$` sigils in front of that variable belong to the
  root: PHP 7's uniform variable syntax reads `$$name['key']` as
  `($$name)['key']`, so the diagnostic names `$$name` — the thing `data_get()`
  has to be handed — rather than `$name`, which holds only its name.
- **Not flagged** — these constructs are outside the standard and are
  deliberately left untouched:
  - **Write-side access** (`$array['key'] = $value`, `$array['key'] .= $more`,
    `$array[] = $value`, `$array['key'] ??= $default`, `$object->property =
    $value`, `++$array['key']`) — `data_get()` reads a value; it cannot stand
    in for an assignment target. This covers every shape the target takes, not
    only the ones carrying an assignment operator:
    - **Destructuring patterns** — `[$row['a'], $row['b']] = $source`,
      `list($object->property) = $source`, `['key' => $row['a']] = $source`,
      and nested combinations of them.
    - **`foreach` targets** — the value (`foreach ($rows as $out['value'])`),
      the key (`foreach ($rows as $out['key'] => $value)`), and destructuring
      patterns in the `as` clause (`foreach ($rows as [$out['a']])`). The
      *subject* of the loop (`foreach ($payload['rows'] as $row)`) is a read
      and is flagged.
    - **Reference binds** (`$reference = &$array['key']`) — `data_get()`
      returns a value, so a rewrite would silently drop the reference and the
      statement would have no compliant form at all. A bitwise and
      (`$mask & $array['flags']`) is an ordinary read and stays flagged.

    A write target names one chain, but the statement may name two: in
    `$target[$payload['index']] = 'set'` only `$target` is assigned into, while
    `$payload` is *read* to work out which slot to write. A chain nested inside
    another accessor's index brackets or dynamic-member braces is therefore
    flagged, however the surrounding write is spelled —
    `foreach ($rows as $target[$payload['index']])`,
    `[$target[$payload['index']]] = $source`,
    `list($target[$payload['index']]) = $source`, and
    `$order->{$payload['member']} = 'set'` each write the outer chain and read
    the inner one. The offset need not be the innermost thing enclosing the
    read: `$target[strtolower($payload['index'])] = 'set'` reads `$payload`
    just the same.
  - **Existence checks** (`isset()`, `empty()`, `unset()`,
    `array_key_exists()`) — these already answer the missing-element question
    that `data_get()`'s fallback exists to solve.
  - **Array literals** (`['key' => $value]`) — a declaration, not a read. Only
    the literal's own syntax is exempt: an accessor used as a literal's key or
    value (`[$row['id'] => $row['name']]`, the shape `mapWithKeys()` callbacks
    are built from) is read to build it, so both sides are reported.
  - **`$this`-rooted access** (`$this->property`, `$this->config['key']`) — an
    object's own state is known to exist, so neither the fallback nor the
    type-agnostic lookup applies.
  - **Method calls** (`$object->method()`, `$object?->method()`) — an
    invocation of the object's own API, which `data_get()` does not resolve.
- **Known blind spots** — four cases accepted rather than approximated: three
  the token stream cannot express, and one defect upstream in PHP_CodeSniffer's
  own tokenizer:
  - Accessors inside interpolated strings (`"{$array['key']}"`) — PHP_CodeSniffer
    hands the whole string over as one token, so there is nothing to inspect.
  - Chains rooted in something that is not a variable (`foo()['key']`,
    `self::CONSTANTS['key']`) — there is no variable to report against. A
    static *property* chain (`self::$registry['key']`) does have one and is
    flagged.
  - Arguments bound to a by-reference parameter (`bump($array['key'])` where
    `function bump(&$value)`) — the call site is a write, but only the callee's
    signature says so, and a sniff sees one file at a time. Such a call is
    reported as a read; silence would require cross-file analysis.
  - A `foreach` whose target is a dynamic member holding a brace-bearing
    expression (`foreach ($rows as $order->{match (true) { ... }})`) — the loop's
    scope goes unrecorded, and the tokenizer then labels the *next* statement's
    destructuring pattern as an index. That label is the only thing separating
    an index (a read) from a pattern (a write), so that statement's write target
    is reported as though it were an offset read. The mislabelling happens
    before any sniff runs, so it is pinned in `tokenizer-limits.inc` — where an
    upstream fix surfaces as a test failure — rather than worked around by
    re-deriving the distinction from token data already known to be wrong.
- **Auto-fixable — No (detection only).** Auto-fix scoping was investigated and
  rejected on two counts. First, the nested rewrite is lossy:
  `$payload['a']['b']` becomes `data_get($payload, 'a.b')`, and that dotted
  path silently changes meaning whenever a key itself contains a dot. Second,
  `data_get()` ships with Laravel, so emitting it into a file outside a Laravel
  application produces code that does not run — a fixer cannot tell the
  difference from the token stream. Choosing the replacement (a `data_get()`
  path, a null-coalescing default, or a redesign that removes the lookup) is a
  judgement call, so the sniff surfaces the read and leaves the rewrite to the
  developer.

Tests covering compliant `data_get()` usage, the out-of-scope boundary
constructs, reads that sit beside a write or an existence check without becoming
one, reads computed inside a write target's offset, per-line violation
reporting, one-diagnostic-per-chain, input PHP itself rejects (chains left
mid-edit on an unclosed bracket, brace, or bare `->`, files ending on a bare
variable, and malformed statements), the tokenizer defect above, and the
detection-only guarantee live at `tests/Standards/ArrayAccessorsTest.php`, with
fixtures under `tests/Standards/Fixtures/ArrayAccessorsSniff/`.

## What remains code review

Whether a flagged read *should* become a `data_get()` call, a null-coalesced
default, or a redesign that avoids the lookup altogether. The sniff points at
every direct read; picking the replacement — and the fallback value it should
carry — is the developer's call.

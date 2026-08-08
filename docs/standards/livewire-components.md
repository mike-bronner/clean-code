# Livewire: Components

## Standard

- Each Livewire component must have a single root element (usually a `div`)
  with no Livewire, Blade, or Alpine attributes.
- Add a unique `wire:key` to each component.
- Components in loops or adjacent to other components must each be wrapped in
  a `<template>` tag with the same `wire:key` as the component.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff, best-effort)

The standard is **partly enforceable**. A Livewire component's markup lives in
its Blade view, and PHPCS reads a `.blade.php` file as a run of `T_INLINE_HTML`
tokens — text, not an element tree. Blade control flow (`@if`, `@include`, a
`@foreach` over runtime data) means the *rendered* DOM shape is not decidable
from the template at all.

So the sniff reads the markup with regular expressions and speaks only about
shapes that are unambiguous in the source. **Detection is heuristic and
best-effort by design**: where the source does not show the answer, nothing is
reported. False negatives are the deliberate trade for not spamming false
positives; the rest of the standard stays with code review.

### Blade views are scanned

`rules.xml` registers `blade.php` in its `extensions` argument, mapped to the
PHP tokenizer:

```xml
<arg name="extensions" value="php,blade.php/php"/>
```

Without that entry PHPCS's file filter skips Blade views outright. With it, a
view's markup arrives as `T_INLINE_HTML` and its embedded PHP is tokenized like
any other PHP — so the rest of the ruleset applies to a view's PHP as well.

#### `Internal.NoCodeFound` is suppressed on Blade views

Handing a Blade view to the PHP tokenizer has one unavoidable side effect. A
view is normally all markup and directives with no raw `<?php ?>` tag anywhere,
and PHPCS answers a file it found no PHP in with `Internal.NoCodeFound` — a
warning, which still exits 1. Unsuppressed, installing this ruleset would fail a
consumer's CI on every idiomatic Blade view, whatever the view contains. So
`rules.xml` turns the check off for Blade views only:

```xml
<rule ref="Internal.NoCodeFound">
    <exclude-pattern>*\.blade\.php</exclude-pattern>
</rule>
```

An `exclude-pattern` rather than `<severity>0</severity>`, so the blast radius
stops at `.blade.php`: a plain `.php` file that genuinely contains no PHP still
warns, which is the case the check exists for.

**The trade:** a `.blade.php` file whose PHP is written with short open tags, on
a runtime that disallows them, is silently unlinted instead of reported. That is
the same silence PHPCS gives any file it cannot tokenize, and it is worth
strictly less than a ruleset that cannot be installed.

### Custom sniff — `CleanCode.Livewire.ComponentMarkup`

One sniff, four codes ([#46](https://github.com/mike-bronner/phpcs-rules/issues/46)).
Each code can be excluded individually where a project's markup defeats the
heuristic behind it.

| Code | Reports |
|---|---|
| `RootElementAttributes` | a component view's first element tag carries a `wire:`, `x-`, `@`, `:`, or `{{ … }}` attribute |
| `MissingWireKeyInLoop` | a `<livewire:…>` tag inside a Blade loop with no `wire:key` |
| `AdjacentComponentNotWrapped` | a `<livewire:…>` tag beside another one, not wrapped in a `<template>` |
| `TemplateKeyMismatch` | the wrapping `<template>`'s `wire:key` differs from the component's, or is absent |

Violations are reported on the **line**, not a token: the analysis runs over
reconstructed markup rather than the token stream, so a line number is the
finest position the sniff can honestly claim.

**Detection only.** Every fix needs a value a machine cannot derive — which
attributes belong on the root, what a `wire:key` should be keyed to, where a
`<template>` wrapper starts — so there is no auto-fix.

### The four narrowings that keep false positives out

Each one is a deliberate loss of coverage, and each one is what stops a whole
class of wrong report.

- **The view must be recognisably Livewire.** Nothing is reported unless the
  file contains a `wire:` attribute, a `<livewire:…>` tag, or an `@livewire`
  directive. "Is this view a Livewire component?" is not otherwise answerable
  from one file, and without the gate every plain Blade partial with an Alpine
  root would be reported.
- **The root-element rule needs a component's *own* view**, not one that merely
  renders a component. A page or layout like this passes the gate above, but
  its outer `<div>` is the *layout's* root, not any component's:

  ```blade
  <div x-data="{ sidebarOpen: false }" class="app-shell">
      <x-nav />
      <livewire:notifications-bell wire:key="bell" />
      <main>{{ $slot }}</main>
  </div>
  ```

  So `RootElementAttributes` also requires the view to carry a `wire:`
  attribute of its own — one outside every `<livewire:…>` tag. Above, the only
  `wire:` is the `wire:key` on the embedded component, so the root is left
  alone. For the same reason a first tag that *opens a component* is never read
  as a root element — neither the `<livewire:…>` invocation itself nor the
  `<template>` this standard requires around adjacent components:

  ```blade
  <template wire:key="header">
      <livewire:panel-header wire:key="header" />
  </template>
  ```

  Both `wire:key` attributes above are the ones `MissingWireKeyInLoop` and
  `TemplateKeyMismatch` demand, so reading either as a root-element violation
  would report the view for obeying the standard.
- **A component is a `<livewire:…>` tag.** `<x-…>` is Blade's component
  namespace, shared with ordinary Blade components that owe no `wire:key` at
  all, so it is not read as a Livewire component here.
- **`@livewire('name', …)` is not analysed.** Its key is a PHP expression
  argument (`key($row->id)`), not an attribute, so neither presence nor
  equality can be read off the source.

Three further silences follow the same principle:

- **An unbalanced loop directive yields no loop.** A `@foreach` whose body
  continues in an `@include`, or an `@endforeach` belonging to a parent view,
  gives the sniff no region it can trust, so components under it are left
  alone.
- **Adjacency is read over siblings, not over every tag.** Livewire's tag
  syntax lets a component wrap content, so the tags are walked with a nesting
  stack. In the markup below, `card-icon` is `card`'s child and neither is
  reported; two `<livewire:card>` elements side by side still are.

  ```blade
  <livewire:card wire:key="a">
      <livewire:card-icon wire:key="icon" />
  </livewire:card>
  ```

  A component left unclosed adopts every later tag as a child rather than
  guessing where its element ended — the same silence the unbalanced loop takes.
- **Comments are blanked before analysis**, so commented-out markup is never
  reported.

### Rejected heuristics

- **Flagging every element in a loop that lacks `wire:key`** — rejected. The
  standard asks for a key on each *component*; a loop's `<li>`, `<td>`, and
  `<option>` elements need none, and reporting them would bury the real
  finding.
- **Counting root elements** — rejected. "Single root element" is a property
  of the *rendered* output. A view whose root is chosen by `@if`, or assembled
  from an `@include`, has no statically knowable root count, so any answer
  would be a guess.
- **Checking `wire:key` uniqueness** — rejected. Uniqueness is a per-render
  property of loop data (`wire:key="row-{{ $row->id }}"` is one literal in the
  source and many keys at runtime). The sniff can see that a key is *present*,
  never that the values it produces differ.

## What remains code review

Root-element *count*, `wire:key` *uniqueness*, adjacency that only exists after
Blade control flow has run, and every `@livewire(…)` call site remain code
review — as does judging whether a heuristic's silence on a given view is
correct. Dedicated Blade/Livewire tooling and Livewire's own runtime warnings
cover what a token-level linter cannot.

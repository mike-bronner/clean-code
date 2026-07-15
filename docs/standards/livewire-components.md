# Livewire: Components

## Standard

- Each Livewire component must have a single root element (usually a `div`)
  with no Livewire, Blade, or Alpine attributes.
- Add a unique `wire:key` to each component.
- Components in loops or adjacent to other components must each be wrapped in
  a `<template>` tag with the same `wire:key` as the component.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

These are **Blade-template rules**. They are **not** enforced by a PHPCS sniff.
Enforcement is via **code review and developer discipline**.

PHPCS tokenizes PHP; a Livewire component's markup lives in its Blade view,
where everything between PHP tags is a single opaque `T_INLINE_HTML` blob —
there are no element tokens to count roots with, no attribute tokens to inspect
for `wire:key`, and Blade directives (`@if`, `@foreach`, `@include`) mean the
*rendered* DOM shape is not even decidable from the template text. "Unique
`wire:key`" is a runtime property of loop data, and "single root element" is a
property of the rendered output — neither is visible to single-file token
analysis.

## Partial enforcement assessment

No token-visible slice was found; no follow-up sniff issue is opened.

- **Blade views** — the markup is `T_INLINE_HTML` to PHPCS; checking root-element
  count or `wire:key` presence would mean parsing HTML-plus-Blade with regexes
  inside one opaque token. That is HTML parsing, not a token heuristic, and
  Blade control flow makes any answer unreliable.
- **Inline components** (a heredoc template returned from `render()`) were
  considered as the one place component markup appears inside a PHP file — but
  the heredoc body is likewise a single string token, so the same
  parse-HTML-inside-a-string problem applies. Rejected.
- Dedicated Blade/Livewire tooling (Blade linters, Livewire's own runtime
  warnings for multiple root elements) is the right layer for automation here,
  not PHPCS.

Resolution: **documentation-only** — the full standard remains enforced by code
review.

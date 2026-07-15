# Naming: Semantic naming principles

## Standard

From Robert Martin's *Clean Code* — a cluster of semantic naming rules:

- **Use Intention-Revealing Names** — the name should tell you why it exists,
  what it does, and how it is used. If a name requires a comment, the name does
  not reveal its intent: `$elapsedTimeInDays` needs no comment; `$d // elapsed
  time in days` does.
- **Avoid Disinformation** — spelling similar concepts similarly is
  information; inconsistent spellings are dis-information. Similar names should
  sort together alphabetically with obvious differences, so readers can tell
  `ProductRecordBuilder` from `ProductReportBuilder` at a glance instead of
  being misled by near-identical shapes.
- **Use Pronounceable Names** — take advantage of the part of the brain evolved
  for spoken language. `$generationTimestamp` can be discussed out loud;
  `$genymdhms` cannot.
- **Use Searchable Names** — single-letter names and numeric constants are hard
  to locate across a body of text. `MAX_CLASSES_PER_STUDENT` can be grepped
  for; a bare `7` cannot.
- **Avoid Mental Mapping** — readers shouldn't have to mentally translate names
  into other names they already know. If `$r` means "the lowercased URL", the
  reader carries that mapping in their head on every line; `$lowercasedUrl`
  costs nothing.
- **Pick One Word Per Concept** — pick one word for one abstract concept and
  stick with it. A codebase that mixes `fetch` / `retrieve` / `get` for the
  same operation forces readers to wonder whether the differences mean
  something.

Takeaways: code should be self-documenting, clarify rather than obscure, have
intention, and be consistent with expectations.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. It is **not** enforced by a PHPCS
sniff. Enforcement is via **code review and developer discipline**.

Whether a name reveals intent, misleads, maps onto the reader's existing
vocabulary, or stays consistent with the concept-word used elsewhere in the
codebase are judgements about *meaning* — they depend on the problem domain,
the reader, and cross-file context that no single file's tokens can decide. A
token stream can see a name's shape, but not whether it tells the truth.

## Partial enforcement assessment

The standard was assessed for narrow, token-based heuristics that could catch
a subset:

- **Searchable names — magic numbers** — *heuristic found.* A bare numeric
  literal in an expression is exactly the unsearchable constant the standard
  calls out, and it is detectable by pure single-file token analysis
  (`T_LNUMBER` / `T_DNUMBER` outside declaration sites). Focused sniff issue:
  [#136](https://github.com/mike-bronner/phpcs-rules/issues/136).
- **Searchable names — short identifiers** — *already tracked.* Single-letter
  and too-short names are covered by the PHPMD rules already in the backlog:
  [#106](https://github.com/mike-bronner/phpcs-rules/issues/106)
  (Naming: ShortVariable),
  [#111](https://github.com/mike-bronner/phpcs-rules/issues/111)
  (Naming: ShortMethodName), and
  [#103](https://github.com/mike-bronner/phpcs-rules/issues/103)
  (Naming: ShortClassName). No new issue opened — it would duplicate those.
- **Pronounceable names** — *no reliable heuristic.* Consonant-cluster or
  dictionary checks misfire constantly on legitimate domain terms, acronyms,
  and non-English identifiers; the noise would swamp the signal.
- **Intention-revealing names, avoiding disinformation, avoiding mental
  mapping, one word per concept** — *out of sniff reach.* These require
  understanding what a name *means* and how it relates to names elsewhere in
  the project; both are beyond single-file token analysis.

The semantic core of the standard — names that tell the truth about intent —
remains enforced by code review.

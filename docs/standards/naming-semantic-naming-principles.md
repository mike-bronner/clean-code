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

## Enforceability — Tier 3 (semantic core not statically enforceable)

This is an architectural / semantic standard. Its core is enforced by **code
review and developer discipline**; one narrow slice carries a sniff.

Whether a name reveals intent, misleads, maps onto the reader's existing
vocabulary, or stays consistent with the concept-word used elsewhere in the
codebase are judgements about *meaning* — they depend on the problem domain,
the reader, and cross-file context that no single file's tokens can decide. A
token stream can see a name's shape, but not whether it tells the truth.

### The sniffed slice — magic numbers

`CleanCode.Naming.DisallowMagicNumbers` warns on a bare numeric literal used
where a named constant belongs — the "Use Searchable Names" rule's numeric
half ([#136](https://github.com/mike-bronner/clean-code/issues/136)).

| | |
|---|---|
| Registers on | `T_LNUMBER`, `T_DNUMBER` |
| Severity | **warning**, not error — some literals are self-evident in context, so the rule must not fail a build |
| Fixer | none. Only the author knows what the number means, and choosing the constant's name is the judgement this standard leaves to review |
| Property | `ignoredNumbers`, an array, default `0, 1, -1` |

It skips the places a literal is *being given* its name — `const` statements,
class constant and enum case declarations, and property and parameter default
values — plus two shapes where naming is impossible or meaningless: a
`declare()` directive, whose value PHP requires to be a literal, and attribute
arguments, which are declarative metadata rather than an evaluated expression.

The ignore list is matched by value, not spelling, in whichever base the
literal is written: an entry of `0` also covers `0.0` and `0x0`, and `1_000`
matches an entry of `1000`. Widen it from a consuming ruleset when a domain has
its own self-evident values:

```xml
<rule ref="CleanCode.Naming.DisallowMagicNumbers">
    <properties>
        <property name="ignoredNumbers" type="array">
            <element value="0"/>
            <element value="1"/>
            <element value="-1"/>
            <element value="100"/>
        </property>
    </properties>
</rule>
```

Magic *strings* are out of scope. Formats, array keys, and SQL fragments make
string literals far noisier at the token level, with no comparable heuristic
for telling a concept from a value.

## Partial enforcement assessment

The standard was assessed for narrow, token-based heuristics that could catch
a subset:

- **Searchable names — magic numbers** — *heuristic found, and shipped.* A bare
  numeric literal in an expression is exactly the unsearchable constant the
  standard calls out, and it is detectable by pure single-file token analysis
  (`T_LNUMBER` / `T_DNUMBER` outside declaration sites). Now enforced by
  `CleanCode.Naming.DisallowMagicNumbers` — see the section above
  ([#136](https://github.com/mike-bronner/clean-code/issues/136)).
- **Searchable names — short identifiers** — *already tracked.* Too-short
  method and function names are enforced by
  `CleanCode.Naming.ShortMethodName` — see
  [PHPMD Naming: ShortMethodName](../phpmd/naming-shortmethodname.md)
  ([#111](https://github.com/mike-bronner/clean-code/issues/111)). The other
  two short-identifier shapes are covered by the PHPMD rules still in the
  backlog: [#106](https://github.com/mike-bronner/clean-code/issues/106)
  (Naming: ShortVariable) and
  [#103](https://github.com/mike-bronner/clean-code/issues/103)
  (Naming: ShortClassName). No new issue opened — it would duplicate those.
- **Pronounceable names** — *no reliable heuristic.* Consonant-cluster or
  dictionary checks misfire constantly on legitimate domain terms, acronyms,
  and non-English identifiers; the noise would swamp the signal.
- **Intention-revealing names, avoiding disinformation, avoiding mental
  mapping, one word per concept** — *out of sniff reach.* These require
  understanding what a name *means* and how it relates to names elsewhere in
  the project; both are beyond single-file token analysis.

## What remains code review

The semantic core — names that tell the truth about intent. The review
obligation is concrete: for every identifier a change introduces or renames,
the reviewer confirms the name states what it is for without a comment, is
pronounceable and greppable, needs no mental translation, and reuses the
concept-word the codebase already uses for that operation.

The magic-number sniff does not narrow that obligation. It says a number wants
a name; whether the name it is given reveals intent is still a judgement only a
reader can make.

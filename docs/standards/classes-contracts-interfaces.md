# Classes: Contracts (Interfaces)

## Standard

- Contracts aim to loosen coupling of objects: coupling shifts from concrete
  implementations to abstract contracts — no logic, only method interfaces.
- Use contracts where they add value: classes instantiated through dependency
  injection, and code used by others (especially packages), letting
  implementations be switched out easily.
- Contracts are not always needed — don't introduce one everywhere by default,
  only where it is useful.

**Why:** provides loose coupling (SOLID).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural/semantic standard: whether a class *should* be
coupled through a contract depends on how it is instantiated and consumed,
which a token-based PHPCS sniff cannot see. **Enforcement is via code review
and developer discipline.** No PHPCS sniff exists or is planned for this
standard.

No partial token-based enforcement is feasible either:

- **"No logic, only method interfaces"** — the PHP engine already forbids
  method bodies in interfaces; a sniff would duplicate a language-level
  guarantee.
- **Interface naming heuristics** (e.g. a required prefix or suffix) — this
  standard says nothing about naming, so a naming sniff would enforce a
  different rule, not a slice of this one.
- **"Type-hint contracts in DI-consumed classes"** — requires knowing which
  classes are container-instantiated and whether a suitable contract exists.
  That is cross-file, semantic knowledge; a PHPCS sniff sees one file's
  tokens at a time.

No subset of the standard survives that, so no partial-enforcement sniff issue
is opened and no rule is wired into `CleanCode/ruleset.xml`. The assessment is recorded on
[#10](https://github.com/mike-bronner/phpcs-rules/issues/10).

## What remains code review

Everything. The judgement calls — does this class benefit from a contract, is
an interface being introduced where it adds nothing, is a package boundary
missing one — are about intent, instantiation, and consumers. Those calls
stay with code review.

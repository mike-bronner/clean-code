# Testing: Databases (SQLite caveats)

## Standard

Do not use SQLite for testing if:

- You are using JSON fields.
- You require exact float value calculations based on decimal fields.
- You have table alterations in your migrations.
- You have raw queries which manipulate dates.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This standard is a conditional on project-wide facts, and no narrow token
heuristic can enforce even a subset of it:

- **The trigger lives outside PHP token streams.** Whether the test suite
  runs on SQLite is declared in `phpunit.xml`
  (`<env name="DB_CONNECTION" value="sqlite"/>`), `.env.testing`, or CI
  configuration — XML and env files that PHPCS never tokenizes.
- **Each caveat is a cross-file conjunction.** The violation is "SQLite as
  the test driver **and** JSON columns / decimal math / table alterations /
  raw date queries elsewhere in the codebase." A PHPCS sniff sees one file's
  tokens at a time and cannot join facts across files, let alone across file
  formats.
- **A blanket heuristic would misstate the standard.** Flagging every
  `'sqlite'` or `:memory:` literal in `tests/` would warn even when none of
  the four caveats applies — but the standard explicitly permits SQLite for
  testing in that case. A sniff stricter than the standard it enforces is a
  false-positive generator, not partial enforcement.

**Conclusion:** no focused sniff issue is opened for this standard.

## What remains code review

Everything. Whether a project's migrations, models, and raw queries put it
inside one of the four caveat conditions is a judgement about the codebase as
a whole; enforcement is via code review and developer discipline.

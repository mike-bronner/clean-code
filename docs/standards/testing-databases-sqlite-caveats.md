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

- **The trigger usually lives outside PHP token streams.** Whether the test
  suite runs on SQLite is normally declared in `phpunit.xml`
  (`<env name="DB_CONNECTION" value="sqlite"/>`), `.env.testing`, or CI
  configuration — XML and env files that PHPCS never tokenizes. It *can*
  surface in a PHP file (`config/database.php`'s connection default), so the
  next point, not this one, is what actually closes the door.
- **Each caveat is a cross-file conjunction.** The violation is "SQLite as
  the test driver **and** JSON columns / decimal math / table alterations /
  raw date queries elsewhere in the codebase." A PHPCS sniff sees one file's
  tokens at a time, so even when the driver *is* readable from
  `config/database.php`, the sniff inspecting a migration has no access to it
  — and the sniff inspecting the config has no access to the migration.
  Neither half can reach the other.
- **A blanket heuristic would misstate the standard.** Flagging every
  `'sqlite'` or `:memory:` literal in `tests/` would warn even when none of
  the four caveats applies — but the standard explicitly permits SQLite for
  testing in that case. A sniff stricter than the standard it enforces is a
  false-positive generator, not partial enforcement.

### Why the sibling *Testing* standards did yield sniff issues

The other Tier 3 testing standards each produced a focused partial-enforcement
issue — Reflection-based access to non-public methods
([#145](https://github.com/mike-bronner/phpcs-rules/issues/145)), mocking
first-party classes
([#146](https://github.com/mike-bronner/phpcs-rules/issues/146)),
test-absence ([#128](https://github.com/mike-bronner/phpcs-rules/issues/128)),
and the suite-boundary checks
([#148](https://github.com/mike-bronner/phpcs-rules/issues/148),
[#149](https://github.com/mike-bronner/phpcs-rules/issues/149),
[#150](https://github.com/mike-bronner/phpcs-rules/issues/150)). In every one
of those, the flagged construct *is itself* the violation: a
`setAccessible(true)` call in a test is wrong on sight, whatever the rest of
the project looks like.

This standard has no such construct. `$table->json()`, `$table->decimal()`,
`->change()` in a migration, and a `DB::raw()` date expression are all
perfectly correct code — the standard does not forbid any of them. It
forbids *pairing* them with an SQLite test driver. The token a sniff could
see is never the defect, so there is no slice to carve off.

**Conclusion:** no focused sniff issue is opened for this standard.

## What remains code review

Everything. Whether a project's migrations, models, and raw queries put it
inside one of the four caveat conditions is a judgement about the codebase as
a whole; enforcement is via code review and developer discipline.

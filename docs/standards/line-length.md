# Line Length

## Standard

- Lines of code should be no longer than **100 characters** and may absolutely
  be no longer than **120 characters**.

**Why:** makes code easier to parse (reduces mental debt, one thought per
line).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (configured existing rule)

`Generic.Files.LineLength` ships with PHP_CodeSniffer and enforces exactly
this standard once configured, so no custom sniff is needed. The master
`rules.xml` sets `lineLimit` to 100 and `absoluteLineLimit` to 120
([#3](https://github.com/mike-bronner/phpcs-rules/issues/3)).

- **Warning above 100** — a line of exactly 100 characters is compliant;
  101–120 characters raises `Generic.Files.LineLength.TooLong` (warning).
- **Error above 120** — a line of exactly 120 characters stays a warning;
  121+ characters raises `Generic.Files.LineLength.MaxExceeded` (error).
- **Reporting only** — where to break a long line is a judgement call, so the
  sniff has no auto-fixer.
- **Tests** — `tests/Rules/LineLengthRulesTest.php` pins the thresholds and
  boundary behaviour against the master ruleset.

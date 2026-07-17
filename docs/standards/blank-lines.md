# Blank Lines

## Standard

- Should only be used to separate concepts.
- At most there should be a single blank line; never multiple.
- There should be no blank lines at the beginning or end of classes, methods,
  or functions.

**Why:** blank lines have meaning and should only be used where appropriate,
providing consistency and improving parsing of code (mental debt, clear code).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (custom sniff)

Enforced by **`CleanCode.WhiteSpace.BlankLines`**, a custom, fully
auto-fixable sniff with three error codes:

- **`ConsecutiveBlankLines`** — two or more blank lines in a row, anywhere in
  the file; the fixer collapses the run to a single blank line.
- **`AfterOpeningBrace`** — blank line(s) directly after the opening brace of
  a class, interface, trait, enum, function, closure, or method; the fixer
  removes them.
- **`BeforeClosingBrace`** — blank line(s) directly before the closing brace
  of the same constructs; the fixer removes them.

Lines inside multi-line tokens (heredocs/nowdocs, multi-line strings) are
never treated as blank, so the fixer cannot alter string contents. Empty and
single-statement bodies produce no false positives.

## Why not an existing sniff

The candidate rules were evaluated against the full unit-test suite
(`CleanCode/Tests/WhiteSpace/BlankLinesUnitTest.inc`, 24 violations, plus the
open-tag fixtures `BlankLinesUnitTest.2.inc` / `.3.inc`, one violation each);
the best available combination catches only 13 of the 24 in the main fixture
(Squiz 2, Slevomat 11) and neither open-tag violation:

- **`Squiz.WhiteSpace.SuperfluousWhitespace`** — its `EmptyLines` check only
  fires *inside* function/closure bodies (guarded by
  `hasCondition([T_FUNCTION, T_CLOSURE])`), so consecutive blank lines at
  file level or between class members are never flagged, and single blank
  lines directly inside braces are ignored entirely.
- **`SlevomatCodingStandard.Classes.EmptyLinesAroundClassBraces`** (configured
  with `linesCountAfterOpeningBrace`/`linesCountBeforeClosingBrace` of `0`) —
  covers class-like braces only; Slevomat ships no equivalent for function or
  method bodies (nothing in `SlevomatCodingStandard.Functions.*` or
  `SlevomatCodingStandard.Whitespaces.*` addresses blank lines around braces).

No configuration of the existing sniffs closes those gaps, and PHPCS's sniff
unit-test harness binds one sniff per test class, so the standard is enforced
by one custom sniff covering all three rules uniformly.

## What remains code review

Whether a *single* blank line actually separates two distinct concepts — or
splits one concept apart — is a semantic judgement no token analyzer can
make. The sniff enforces the mechanical rules; the "concepts" half of the
standard stays with code review.

# Debt: Technical Debt

## Standard

Technical debt is a collection of design or implementation constructs that are
expedient in the short term but set up a technical context that makes future
changes more costly or impossible. It can be created passively as the team
learns more about the problem, or deliberately for expediency.

**Takeaways:** address technical debt as soon as possible after it is
recognized; anyone on the team can identify technical debt.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. Its core is **not**
enforceable by a PHPCS sniff; enforcement is via **code review and developer
discipline**. One narrow slice — the self-declared debt marker — *is*
token-visible and is enforced, as set out below. The tier still describes the
standard, not that slice.

Whether a construct is technical debt is a judgement about the *future cost of
change*: it depends on where the design is heading, what the team has since
learned about the problem, and whether a shortcut was taken deliberately or
discovered in hindsight. A token-based PHPCS sniff inspects one file's tokens in
isolation at lint time — it has no model of intent, history, or the changes the
code will later resist. And "address it as soon as possible after it is
recognized" is a process obligation on the team, not a property of any file.

## Partial enforcement — self-declared debt markers

One narrow, token-visible slice was found and is now enforced: **self-declared
debt markers**. A `TODO` / `FIXME` / `HACK` / `XXX` comment is a developer's
explicit acknowledgement of debt left unaddressed — recognition of debt, in
token form, readable by single-file analysis. It landed with
[#138](https://github.com/mike-bronner/clean-code/issues/138).

Three sniffs carry it, split by what PHPCS core already ships:

| Keyword | Sniff | Codes |
|---|---|---|
| `TODO` | `Generic.Commenting.Todo` (core) | `.TaskFound`, `.CommentFound` |
| `FIXME` | `Generic.Commenting.Fixme` (core) | `.TaskFound`, `.CommentFound` |
| `HACK` | `CleanCode.Commenting.DebtMarkers` (custom) | `.HackTaskFound`, `.HackCommentFound` |
| `XXX` | `CleanCode.Commenting.DebtMarkers` (custom) | `.XxxTaskFound`, `.XxxCommentFound` |

The two core sniffs are wired into `CleanCode/ruleset.xml` rather than reimplemented. The
custom sniff exists only because core ships no equivalent for `HACK` or `XXX`;
it mirrors the core pair's registered tokens, keyword pattern and message shape
so the four keywords behave as one rule. Every code reports the keyword in its
message, so `HACK` is distinguishable from `TODO` in a summarized report.

All four are found in line comments (both `//` and `#`), block comments and
docblocks, because all three sniffs register every comment token PHPCS produces
except the `phpcs:` annotations. The `.TaskFound` half of each pair carries the
text following the keyword; `.CommentFound` is the marker standing alone.

**Warnings, never errors.** `Generic.Commenting.Fixme` reports errors out of the
box and `CleanCode/ruleset.xml` lowers it with `<type>warning</type>` to match the other
two. A marker is honest documentation of known debt: keeping it visible is the
point, and failing a build over it invites deleting the comment instead of
paying the debt. For the same reason none of the three is auto-fixable — the
only mechanical rewrite available is deleting the comment, which drops the
recognition and pays nothing.

## What stays with code review

The sniffs catch only *self-declared* debt. Everything else about the standard
remains a review obligation:

- **Undeclared debt** — an expedient design nobody wrote a marker for. No token
  scan can see it.
- **Whether a construct makes future change costlier** — the judgement the
  standard is actually about.
- **Whether the debt is addressed promptly.** A marker's *presence* is
  reportable; its age, and whether anyone is paying it down, are not properties
  of any single file.

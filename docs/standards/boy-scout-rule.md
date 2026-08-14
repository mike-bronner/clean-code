# Boy Scout Rule

## Standard

From Robert Martin's *Clean Coder*: "Leave the campground cleaner than you found
it." If we all checked in our code a little cleaner than when we checked it out,
the code could not rot. The cleanup doesn't have to be big — change one variable
name for the better, break up one function that's too large, eliminate one small
bit of duplication, clean up one composite `if` statement.

**Takeaway:** improve each file you touch during a PR to continuously improve the
project over time.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced by
a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time. It
has no notion of a pull request's diff, of which files were *touched*, or of
whether a change left a file *better* than it was. The Boy Scout Rule is exactly
those things: an incremental, PR-scoped improvement whose "for the better" is a
subjective quality judgement. No amount of token analysis can decide it.

## Partial enforcement assessment

No slice of this rule is catchable by a token-based sniff, so **no sniff is
planned**.

The concrete examples the rule cites — rename a variable "for the better", break
up a function "that's too large", eliminate "one small bit of duplication",
simplify a "composite `if` statement" — are either subjective ("for the better")
or belong to *separate* standards (function length, cyclomatic complexity,
duplication detection) that are catalogued and enforced on their own terms. The
Boy Scout Rule itself is the meta-discipline of applying such improvements to
every file a PR touches, which requires diff and PR awareness a sniff does not
have.

If a future, narrower heuristic is identified, open a focused sniff issue for
that specific slice and link it here.

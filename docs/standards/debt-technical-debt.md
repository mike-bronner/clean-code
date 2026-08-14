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

This is an architectural / semantic / process standard. It is **not** enforced by
a PHPCS sniff. Enforcement is via **code review and developer discipline**.

Whether a construct is technical debt is a judgement about the *future cost of
change*: it depends on where the design is heading, what the team has since
learned about the problem, and whether a shortcut was taken deliberately or
discovered in hindsight. A token-based PHPCS sniff inspects one file's tokens in
isolation at lint time — it has no model of intent, history, or the changes the
code will later resist. And "address it as soon as possible after it is
recognized" is a process obligation on the team, not a property of any file.

## Partial enforcement assessment

One narrow, token-visible slice was found: **self-declared debt markers**. A
`TODO` / `FIXME` / `HACK` comment is a developer's explicit acknowledgement of
debt left unaddressed — recognition of debt, in token form, readable by
single-file analysis (PHPCS core already ships `Generic.Commenting.Todo` and
`Generic.Commenting.Fixme` for exactly this). Per this standard's execution
process, that slice is not implemented here; it is tracked as a focused sniff
issue: [#138](https://github.com/mike-bronner/phpcs-rules/issues/138).

Everything else about the standard — recognizing *undeclared* debt, judging
whether a construct makes future change costlier, and the discipline of paying
debt down promptly — remains enforced by code review.

# Clear Code: Group Code By Concepts

## Standard

Multiple ideas together form a concept. Combine multiple statements into
groups separated by empty lines.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Example

Ungrouped — one uninterrupted run of statements, no visual structure:

```php
public function registerUser(array $input): User
{
    $email = strtolower(trim($input['email']));
    $name = trim($input['name']);
    $user = new User();
    $user->email = $email;
    $user->name = $name;
    $user->save();
    $this->mailer->sendWelcome($user);
    $this->logger->info('User registered.', ['id' => $user->id]);
    return $user;
}
```

Grouped by concept — normalize input, build and persist, notify, return —
each concept separated by a blank line:

```php
public function registerUser(array $input): User
{
    $email = strtolower(trim($input['email']));
    $name = trim($input['name']);

    $user = new User();
    $user->email = $email;
    $user->name = $name;
    $user->save();

    $this->mailer->sendWelcome($user);
    $this->logger->info('User registered.', ['id' => $user->id]);

    return $user;
}
```

## Enforceability — Tier 3 (not statically enforceable)

This standard is classified **Tier 3** and the tracking issue carries the
`not-lintable` label: what constitutes a *concept* is semantic. A token-based
PHPCS sniff sees statements and blank lines, but it cannot tell whether two
adjacent statements express one idea or two — that judgement depends on
domain knowledge and intent, which are invisible at the token level. A sniff
could neither confirm that existing blank lines fall on concept boundaries
nor propose where missing ones belong. Enforcement is via code review and
developer discipline.

## Partial-enforcement assessment

A narrow token heuristic **can catch a partial slice** of this standard: the
degenerate case where no grouping was attempted at all.

- **Detection** — a run of more than a configured number of consecutive
  statements inside a function body with no blank-line separator anywhere is
  flagged as an ungrouped block. Statement runs and blank lines are both
  token-visible.
- **Configurable threshold** — the maximum run length is a sniff property;
  around 8 consecutive statements is a plausible default.
- **Warning severity, not error** — a long run may legitimately be a single
  cohesive concept, so the sniff would point at grouping candidates rather
  than mandate a fix.
- **What it cannot check** — that blank lines fall on actual concept
  boundaries. The heuristic verifies that *some* visual grouping exists, not
  that the groups are *concepts*, so the standard remains Tier 3 overall.

Per the tracking issue, opening a focused sniff issue for this slice is left
to a human PBI author; none is created as part of the documentation issue.

## What remains code review

Everything beyond the degenerate case: whether statements that sit together
actually form one idea, whether a blank line splits a single concept in two,
and whether a group is better extracted into a named method. Those are calls
about meaning, not tokens — they stay with code review.

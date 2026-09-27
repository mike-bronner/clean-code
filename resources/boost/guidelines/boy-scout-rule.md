# Boy Scout Rule

From Robert Martin's *Clean Coder*: "Leave the campground cleaner than you found
it." If we all checked in our code a little cleaner than when we checked it out,
the code could not rot. The cleanup doesn't have to be big — change one variable
name for the better, break up one function that's too large, eliminate one small
bit of duplication, clean up one composite `if` statement.

**Takeaway:** improve each file you touch during a PR to continuously improve the
project over time.

## Compliant

```php
public function isOverdue(): bool
{
    return $this->dueAt->isPast();
}
```

## Non-compliant

```php
public function chk(): bool
{
    return $this->d->isPast();
}
```

## Enforcement

Enforced by code review only. No sniff checks this standard.

Standard: [docs/standards/boy-scout-rule.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/boy-scout-rule.md)

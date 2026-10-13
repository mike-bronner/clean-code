# PHPMD Design: GotoStatement

- Do not use `goto`.
- A reader has to find the label to know what runs next, and no tool follows the
  jump. Use standard control structures and separate methods instead.

## Compliant

```php
if ($param === 42) {
    return $this->fallback();
}

return 42;
```

## Non-compliant

```php
if ($param === 42) {
    goto fallback;
}

return 42;

fallback:
return $this->fallback();
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.PHP.DiscourageGoto` | no |

Code review checks the parts of this standard that the sniffs cannot see.

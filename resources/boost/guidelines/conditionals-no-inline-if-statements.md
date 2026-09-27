# Conditionals: No Inline If-Statements

- Do not use inline if-statements.

## Compliant

```php
if ($user === null) {
    return;
}
```

## Non-compliant

```php
if ($user === null) return;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.ControlStructures.InlineControlStructure` | yes |

Standard: [docs/standards/conditionals-no-inline-if-statements.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/conditionals-no-inline-if-statements.md)

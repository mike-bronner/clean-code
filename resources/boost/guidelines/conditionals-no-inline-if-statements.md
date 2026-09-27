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

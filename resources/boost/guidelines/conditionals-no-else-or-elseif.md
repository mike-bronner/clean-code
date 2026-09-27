# Conditionals: No else or elseif

- If you have to use if-statements in PHP, never use `else` or `elseif`.

## Compliant

```php
if ($user === null) {
    return 'Guest';
}

return $user->name;
```

## Non-compliant

```php
if ($user === null) {
    return 'Guest';
} else {
    return $user->name;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.DisallowElse` | yes |

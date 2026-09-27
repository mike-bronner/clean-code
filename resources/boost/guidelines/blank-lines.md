# Blank Lines

- Should only be used to separate concepts.
- At most there should be a single blank line; never multiple.
- There should be no blank lines at the beginning or end of classes, methods,
  or functions.

## Compliant

```php
public function total(): int
{
    $subtotal = $this->subtotal();

    return $subtotal + $this->shipping();
}
```

## Non-compliant

```php
public function total(): int
{

    $subtotal = $this->subtotal();


    return $subtotal + $this->shipping();

}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.WhiteSpace.BlankLines` | yes |

Standard: [docs/standards/blank-lines.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/blank-lines.md)

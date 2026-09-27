# Conditionals: Mapping Arrays

Use mapping arrays instead of multiple if-statements when inspecting different
values of the same variable.

## Compliant

```php
$label = [
    'draft' => 'Draft',
    'paid' => 'Paid',
    'void' => 'Voided',
][$status];
```

## Non-compliant

```php
if ($status === 'draft') {
    $label = 'Draft';
}

if ($status === 'paid') {
    $label = 'Paid';
}

if ($status === 'void') {
    $label = 'Voided';
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.MappingArrayCandidate` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/conditionals-mapping-arrays.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/conditionals-mapping-arrays.md)

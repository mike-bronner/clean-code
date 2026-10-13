# PHPMD Naming: BooleanGetMethodName

- A method that returns a boolean is not named `get…()`. Name it `is…()` or
  `has…()`.
- A getter promises a value. A yes-or-no answer should read as a question at
  every call site.
- **Exemption:** a legacy Eloquent accessor named `get{Name}Attribute()`, with
  `{Name}` starting in an uppercase letter, does not report when it is declared
  on a model. Laravel needs that name to expose `$model->hasDetails`. A class is
  a model when it extends `Illuminate\Database\Eloquent\Model` (or Laravel's
  `User`, `Pivot` or `MorphPivot`), or sits under a `Models` namespace segment.
  This is the same test `CleanCode.Naming.ModelNamingConventions` applies, and
  that sniff still reports the accessor as legacy style: the fix is
  `Attribute::make()`.

## Compliant

```php
public function isActive(): bool
{
    return $this->active;
}
```

## Non-compliant

```php
public function getActive(): bool
{
    return $this->active;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.BooleanGetMethodName` | no |

Code review checks the parts of this standard that the sniffs cannot see.

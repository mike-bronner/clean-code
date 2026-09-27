# PHPMD CodeSize: ExcessiveMethodLength

- A method or function is shorter than 100 lines. The sniff reports it at 100
  lines or more, blank lines and comments included.
- A method that long does several jobs at once. Extract helpers until each one
  states a single intent.

## Compliant

```php
public function import(string $path): void
{
    $rows = $this->reader->read($path);
    $records = $this->mapper->map($rows);

    $this->repository->store($records);
}
```

## Non-compliant

```php
public function import(string $path): void
{
    $handle = fopen($path, 'r');
    // ... reading, mapping, validating and storing, 100 lines in one body
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Functions.ExcessiveMethodLength` | no |

Code review checks the parts of this standard that the sniffs cannot see.

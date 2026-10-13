# Indentation: Multi-Line Statements

- If a single statement extends over multiple lines, any lines subsequent to
  the first should be indented by one level.
- A multi-line call puts its arguments two levels in and its closing
  parenthesis one level in, from the line its expression starts on. A method
  chain that follows the call sits at the closing parenthesis's level.

## Compliant

```php
$invoice = new Invoice(
        customer: $customer,
        total: $total,
    );

return $this->hasManyDeep(
        TextualApparatusEntry::class,
        [Book::class, Chapter::class],
    )
    ->whereColumn('versions.id', 'books.version_id')
    ->orderBy('entry_order');
```

## Non-compliant

```php
$invoice = new Invoice(
    customer: $customer,
    total: $total,
);

return $this->hasManyDeep(
    TextualApparatusEntry::class,
    [Book::class, Chapter::class],
)
    ->whereColumn('versions.id', 'books.version_id')
    ->orderBy('entry_order');
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.WhiteSpace.MultiLineStatementIndent` | yes |

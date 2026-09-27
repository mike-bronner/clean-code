# Naming: Semantic naming principles

From Robert Martin's *Clean Code* — a cluster of semantic naming rules:

- **Use Intention-Revealing Names** — the name should tell you why it exists,
  what it does, and how it is used. If a name requires a comment, the name does
  not reveal its intent: `$elapsedTimeInDays` needs no comment; `$d // elapsed
  time in days` does.
- **Avoid Disinformation** — spelling similar concepts similarly is
  information; inconsistent spellings are dis-information. Similar names should
  sort together alphabetically with obvious differences, so readers can tell
  `ProductRecordBuilder` from `ProductReportBuilder` at a glance instead of
  being misled by near-identical shapes.
- **Use Pronounceable Names** — take advantage of the part of the brain evolved
  for spoken language. `$generationTimestamp` can be discussed out loud;
  `$genymdhms` cannot.
- **Use Searchable Names** — single-letter names and numeric constants are hard
  to locate across a body of text. `MAX_CLASSES_PER_STUDENT` can be grepped
  for; a bare `7` cannot.
- **Avoid Mental Mapping** — readers shouldn't have to mentally translate names
  into other names they already know. If `$r` means "the lowercased URL", the
  reader carries that mapping in their head on every line; `$lowercasedUrl`
  costs nothing.
- **Pick One Word Per Concept** — pick one word for one abstract concept and
  stick with it. A codebase that mixes `fetch` / `retrieve` / `get` for the
  same operation forces readers to wonder whether the differences mean
  something.

Takeaways: code should be self-documenting, clarify rather than obscure, have
intention, and be consistent with expectations.

## Compliant

```php
private const MAX_CLASSES_PER_STUDENT = 7;

$elapsedTimeInDays = $start->diffInDays($end);
```

## Non-compliant

```php
$d = $start->diffInDays($end);

if ($classes > 7) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.DisallowMagicNumbers` | no |
| `CleanCode.Naming.ShortMethodName` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/naming-semantic-naming-principles.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/naming-semantic-naming-principles.md)

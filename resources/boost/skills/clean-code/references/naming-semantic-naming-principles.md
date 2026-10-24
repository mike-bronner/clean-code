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
- **Use Searchable Names** — single-letter names and bare numbers are hard to
  locate across a body of text. `$maxClassesPerStudent` can be grepped for; a
  bare `7` cannot.
- **Avoid Mental Mapping** — readers shouldn't have to mentally translate names
  into other names they already know. If `$r` means "the lowercased URL", the
  reader carries that mapping in their head on every line; `$lowercasedUrl`
  costs nothing.
- **Pick One Word Per Concept** — pick one word for one abstract concept and
  stick with it. A codebase that mixes `fetch` / `retrieve` / `get` for the
  same operation forces readers to wonder whether the differences mean
  something.

The sniffs report a variable, property, parameter, method or class name shorter
than 3 characters.

**Exemption:** a method named `up()` does not report when the class that
declares it extends Laravel's `Illuminate\Database\Migrations\Migration`.
Laravel calls a migration's `up()` and `down()` by name, so the class cannot
rename them. This covers the anonymous class a migration file returns and a
named class, whether the parent is imported or written in full. `up()` still
reports in any other class, in a trait, and as a plain function. Other short
names, such as `go()`, still report inside a migration.

Name a number with a protected typed property, such as
`protected int $maxClassesPerStudent = 7;`. Do not add a constant. A property
default in a class body does not report.

**Exemption:** in a test file, a number does not report when it is the whole
right-hand side of a plain `=` assignment to a named variable or to a named
property of a variable, such as `$count = 2;` or `$this->seconds = 1.5;`. The
name on the left already makes the value searchable, and a Pest file has no
class body to hold a property. A number inside arithmetic, such as
`$this->seconds = 2 * 60;`, still reports, and so does every number in
application code. The sniff reads a file as a test file when its path matches
the `testFilePatterns` setting, which defaults to `*/tests/*`, `*/Tests/*` and
`*Test.php`.

Takeaways: code should be self-documenting, clarify rather than obscure, have
intention, and be consistent with expectations.

## Compliant

```php
protected int $maxClassesPerStudent = 7;

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
| `CleanCode.Naming.ShortVariable` | no |
| `CleanCode.Naming.ShortClassName` | no |

Code review checks the parts of this standard that the sniffs cannot see.

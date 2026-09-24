# Use Statements: Sort Alphabetically

## Standard

- Use statements should be ordered alphabetically.

**Why:**

- Easier to parse, especially with many entries (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniffs)

Fully enforceable, but by **three** Slevomat sniffs rather than one, all wired
into the master `CleanCode/ruleset.xml`
([#67](https://github.com/mike-bronner/clean-code/issues/67)):

| Sniff | Role |
|---|---|
| [`Namespaces.AlphabeticallySortedUses`](https://github.com/slevomat/coding-standard/blob/master/doc/namespaces.md#slevomatcodingstandardnamespacesalphabeticallysorteduses-) | the sorting check itself |
| [`Namespaces.DisallowGroupUse`](https://github.com/slevomat/coding-standard/blob/master/doc/namespaces.md#slevomatcodingstandardnamespacesdisallowgroupuse) | closes the group-use bypass |
| [`Namespaces.MultipleUsesPerLine`](https://github.com/slevomat/coding-standard/blob/master/doc/namespaces.md#slevomatcodingstandardnamespacesmultipleusesperline) | closes the comma-separated bypass |

### Detection

- **One diagnostic per block, at the first out-of-order import**
  (`SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses.IncorrectlyOrderedUses`).
  Sortedness is a property of the whole `use` block, not of each entry: the
  fixer reorders the entire block, so once the first offender is resolved every
  later entry is already in place. A diagnostic per out-of-order entry would
  report the same single defect several times over.
- **Imports sort by their fully qualified name**, not by the alias they are
  bound to — `use App\Support\Timezone as Amsterdam;` belongs after
  `use App\Support\Clock;`.
- **Blank lines and comments between imports do not start a new block.** Every
  import in the file is read as one list, so a visually separate block still has
  to sort against the imports above it.
- **Class, function, and constant imports form three groups, in that order**
  (the sniff's `psr12Compatible` default), each sorted within itself.
  Comparison is case-insensitive (its `caseSensitive` default). Both defaults
  are kept deliberately: they match how a reader scans an import block.
- A file with a single import has nothing to compare and is left alone.

### Why three sniffs

`AlphabeticallySortedUses` on its own is defeatable, in two ways that both let a
plainly unsorted file exit clean:

```php
use App\Nested\{Alpha, Beta};  // a group use anywhere in the file...
use App\Zulu;                  // ...and these two are never checked at all
use App\Charlie;
```

```php
use App\Zulu, App\Alpha;       // only the first type is read, so nothing to sort
```

The sniff abandons a file entirely once it meets a group use, and reads only the
first type of a comma-separated `use`. `DisallowGroupUse` and
`MultipleUsesPerLine` reject both syntaxes outright
(`...DisallowGroupUse.DisallowedGroupUse` and
`...MultipleUsesPerLine.MultipleUsesPerLine`), so neither shape can ship green.
That is what makes the standard fail-closed rather than merely "sorted, unless
you write your imports a different way".

### Auto-fixing

- **Flat unsorted blocks are auto-fixable.** `phpcbf` reorders the whole block
  in one pass and a re-check comes back clean.
- **The two bypass syntaxes are reported but not auto-fixed.** Both diagnostics
  are detection-only, because splitting a group or comma-separated import into
  single-line imports is a restructuring the fixer cannot make safely. Convert
  them by hand; the sorting fixer then applies normally.

Ruleset-integration tests covering compliant code, the single-import edge case,
block-level violation reporting, the auto-fixer, and both bypasses (in both
directions — that they defeat the sorting sniff on its own, and that the
companion sniffs still refuse them) live at `tests/Ruleset/SortedUsesTest.php`.

## What remains code review

Nothing — this standard is fully machine-enforced.

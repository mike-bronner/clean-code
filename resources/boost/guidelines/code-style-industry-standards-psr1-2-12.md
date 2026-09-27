# Code Style: Industry Standards (PSR1/2/12)

All code style must adhere to the following PHP standards:

- [PSR-1](https://www.php-fig.org/psr/psr-1/) — basic coding standard: PHP
  tags, UTF-8 without BOM, declarations vs. side effects, namespace and class
  naming, constant and method naming.
- [PSR-2](https://www.php-fig.org/psr/psr-2/) — coding style guide
  (superseded by PSR-12, whose rules incorporate and update it).
- [PSR-12](https://www.php-fig.org/psr/psr-12/) — extended coding style:
  files and lines, declare statements, namespace and import formatting,
  classes, properties, methods, control structures, operators, and closures.

## Compliant

```php
<?php

declare(strict_types=1);

namespace App\Billing;

class Invoice
{
    public function total(): int
    {
        return $this->subtotal;
    }
}
```

## Non-compliant

```php
<?php
namespace App\Billing;
class invoice {
    function Total() { return $this->subtotal; }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.ControlStructures.InlineControlStructure` | yes |
| `Generic.Files.ByteOrderMark` | no |
| `Generic.Files.LineEndings` | yes |
| `Generic.Files.LineLength` | no |
| `Generic.Formatting.DisallowMultipleStatements` | yes |
| `Generic.Functions.FunctionCallArgumentSpacing` | yes |
| `Generic.NamingConventions.UpperCaseConstantName` | no |
| `Generic.PHP.DisallowAlternativePHPTags` | yes |
| `Generic.PHP.DisallowShortOpenTag` | yes |
| `Generic.PHP.LowerCaseConstant` | yes |
| `Generic.PHP.LowerCaseKeyword` | yes |
| `Generic.PHP.LowerCaseType` | yes |
| `Generic.WhiteSpace.DisallowTabIndent` | yes |
| `Generic.WhiteSpace.IncrementDecrementSpacing` | yes |
| `Generic.WhiteSpace.ScopeIndent` | yes |
| `PEAR.Functions.ValidDefaultValue` | no |
| `PSR1.Classes.ClassDeclaration` | no |
| `PSR1.Files.SideEffects` | no |
| `PSR1.Methods.CamelCapsMethodName` | no |
| `PSR2.Classes.ClassDeclaration` | yes |
| `PSR2.Classes.PropertyDeclaration` | yes |
| `PSR2.ControlStructures.ElseIfDeclaration` | yes |
| `PSR2.ControlStructures.SwitchDeclaration` | yes |
| `PSR2.Files.ClosingTag` | yes |
| `PSR2.Files.EndFileNewline` | yes |
| `PSR2.Methods.FunctionCallSignature` | yes |
| `PSR2.Methods.FunctionClosingBrace` | yes |
| `PSR2.Methods.MethodDeclaration` | yes |
| `PSR12.Classes.AnonClassDeclaration` | yes |
| `PSR12.Classes.ClassInstantiation` | yes |
| `PSR12.Classes.ClosingBrace` | no |
| `PSR12.Classes.OpeningBraceSpace` | yes |
| `PSR12.ControlStructures.BooleanOperatorPlacement` | yes |
| `PSR12.ControlStructures.ControlStructureSpacing` | yes |
| `PSR12.Files.DeclareStatement` | yes |
| `PSR12.Files.FileHeader` | yes |
| `PSR12.Files.ImportStatement` | yes |
| `PSR12.Files.OpenTag` | yes |
| `PSR12.Functions.NullableTypeDeclaration` | yes |
| `PSR12.Functions.ReturnTypeDeclaration` | yes |
| `PSR12.Keywords.ShortFormTypeKeywords` | yes |
| `PSR12.Namespaces.CompoundNamespaceDepth` | no |
| `PSR12.Properties.ConstantVisibility` | no |
| `PSR12.Traits.UseDeclaration` | yes |
| `Squiz.Classes.ValidClassName` | no |
| `Squiz.ControlStructures.ControlSignature` | yes |
| `Squiz.ControlStructures.ForEachLoopDeclaration` | yes |
| `Squiz.ControlStructures.ForLoopDeclaration` | yes |
| `Squiz.ControlStructures.LowercaseDeclaration` | yes |
| `Squiz.Functions.FunctionDeclaration` | no |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing` | yes |
| `Squiz.Functions.LowercaseFunctionKeywords` | yes |
| `Squiz.Functions.MultiLineFunctionDeclaration` | yes |
| `Squiz.Scope.MethodScope` | no |
| `Squiz.WhiteSpace.CastSpacing` | yes |
| `Squiz.WhiteSpace.ControlStructureSpacing` | yes |
| `Squiz.WhiteSpace.ScopeClosingBrace` | yes |
| `Squiz.WhiteSpace.ScopeKeywordSpacing` | yes |
| `Squiz.WhiteSpace.SuperfluousWhitespace` | yes |

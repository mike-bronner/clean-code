<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\NamespaceHelper;
use SlevomatCodingStandard\Helpers\UseStatement;
use SlevomatCodingStandard\Helpers\UseStatementHelper;

final class ClassNames
{
    public function resolve(File $phpcsFile, string $name, int $stackPtr): string
    {
        return match (true) {
            count(NamespaceHelper::getAllNamespacesPointers($phpcsFile)) <= 1,
            NamespaceHelper::isFullyQualifiedName($name) => NamespaceHelper::resolveClassName(
                    $phpcsFile,
                    $name,
                    $stackPtr
                ),
            default => $this->resolveInBlock($phpcsFile, $name, $stackPtr),
        };
    }

    private function resolveInBlock(File $phpcsFile, string $name, int $stackPtr): string
    {
        $imports = $this->blockImports($phpcsFile, $stackPtr);
        $parts = NamespaceHelper::getNameParts($name);
        $whole = $imports[UseStatement::getUniqueId(UseStatement::TYPE_CLASS, $name)] ?? null;
        $first = $imports[UseStatement::getUniqueId(UseStatement::TYPE_CLASS, $parts[0])] ?? null;
        $namespace = (string) NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        return match (true) {
            $whole !== null => "\\{$whole->getFullyQualifiedTypeName()}",
            $first !== null && count($parts) > 1 => implode('\\', [
                '',
                $first->getFullyQualifiedTypeName(),
                ...array_slice($parts, 1),
            ]),
            default => rtrim("\\{$namespace}", '\\') . "\\{$name}",
        };
    }

    private function blockImports(File $phpcsFile, int $stackPtr): array
    {
        $block = NamespaceHelper::findCurrentNamespacePointer($phpcsFile, $stackPtr);
        $imports = [];

        foreach (UseStatementHelper::getFileUseStatements($phpcsFile) as $groupPtr => $group) {
            $groupBlock = NamespaceHelper::findCurrentNamespacePointer($phpcsFile, $groupPtr + 1);
            $imports += match ($groupBlock) {
                $block => $group,
                default => [],
            };
        }

        return $imports;
    }
}

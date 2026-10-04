<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use MikeBronner\CleanCode\Helpers\Declarations;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class DisallowRepositoryClassesSniff implements Sniff
{
    public const DECLARATION_KEYWORDS = [
        T_CLASS => 'Class',
        T_ENUM => 'Enum',
        T_INTERFACE => 'Interface',
        T_TRAIT => 'Trait',
    ];

    public const NON_DECLARATION_KEYWORDS = [
        T_FUNCTION,
    ];

    private const NAME_SUFFIXES = [
        'repository',
        'repositoryinterface',
    ];

    private const NAMESPACE_SEGMENT = 'repositories';

    private const REMEDY = 'the model is the repository, so move the persistence behaviour onto the'
        . ' model instead of declaring a dedicated repository type (see'
        . ' resources/boost/guidelines/pattern-repository.md and'
        . ' resources/boost/guidelines/models-persistence-methods-repository-pattern.md)';

    public function register(): array
    {
        return array_keys(self::DECLARATION_KEYWORDS);
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $name = (new Declarations())->name($phpcsFile, $stackPtr);

        if ($name === null) {
            return;
        }

        $keyword = self::DECLARATION_KEYWORDS[$phpcsFile->getTokens()[$stackPtr]['code']];

        if ($this->hasRepositoryName($name) === true) {
            $phpcsFile->addWarning(
                    '%s %s names itself a repository: ' . self::REMEDY,
                    $stackPtr,
                    'Found',
                    [$keyword, $name]
                );

            return;
        }

        $namespace = $this->repositoryNamespace($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return;
        }

        $phpcsFile->addWarning(
                '%s %s is declared in the %s namespace: ' . self::REMEDY,
                $stackPtr,
                'Found',
                [$keyword, $name, $namespace]
            );
    }

    private function hasRepositoryName(string $name): bool
    {
        $lowercased = strtolower($name);

        foreach (self::NAME_SUFFIXES as $suffix) {
            if (str_ends_with($lowercased, $suffix) === true) {
                return true;
            }
        }

        return false;
    }

    private function repositoryNamespace(File $phpcsFile, int $stackPtr): ?string
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return null;
        }

        $segments = array_map('strtolower', explode('\\', $namespace));

        return in_array(self::NAMESPACE_SEGMENT, $segments, true) === true ? $namespace : null;
    }
}

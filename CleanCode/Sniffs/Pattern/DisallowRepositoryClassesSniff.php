<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

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

    // The declaration tokens getDeclarationName() accepts but this standard
    // does not report. Together with DECLARATION_KEYWORDS this must cover that
    // whole family, so a token added to it fails the suite rather than passing
    // unreported.
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
        . ' docs/standards/pattern-repository.md and'
        . ' docs/standards/models-persistence-methods-repository-pattern.md)';

    public function register(): array
    {
        return array_keys(self::DECLARATION_KEYWORDS);
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A keyword with no name after it is what PHP_CodeSniffer hands a sniff
        // for a file caught mid-edit. Neither half of the convention can be
        // read from it — the name is missing, and reporting the namespace half
        // would leave the message with nothing to point at — so it passes over
        // rather than flagging a declaration the developer is still typing.
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

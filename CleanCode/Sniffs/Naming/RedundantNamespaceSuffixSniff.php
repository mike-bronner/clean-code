<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class RedundantNamespaceSuffixSniff implements Sniff
{
    private const DECLARATION_KEYWORDS = [
        T_CLASS => 'Class',
        T_ENUM => 'Enum',
        T_INTERFACE => 'Interface',
        T_TRAIT => 'Trait',
    ];

    private const APPLICATION_ROOT = 'app';

    private const IRREGULAR_PLURALS = [
        'analyses' => 'analysis',
        'children' => 'child',
        'criteria' => 'criterion',
        'indices' => 'index',
        'matrices' => 'matrix',
        'people' => 'person',
    ];

    public function register(): array
    {
        return array_keys(self::DECLARATION_KEYWORDS);
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A keyword with no name after it is what PHP_CodeSniffer hands a sniff
        // for a file caught mid-edit. There is no name to read a suffix off, so
        // the declaration passes over rather than being reported while the
        // developer is still typing it.
        if ($name === null) {
            return;
        }

        // Deepest segment first: the folder nearest the declaration is the one
        // whose name a developer echoes, so it is the one the message names.
        foreach (array_reverse($this->segmentsBelowApplicationRoot($phpcsFile, $stackPtr)) as $segment) {
            $suffix = $this->redundantSuffix($name, $segment);

            if ($suffix === null) {
                continue;
            }

            $phpcsFile->addError(
                "%s %s repeats its own %s namespace segment: drop the redundant \"%s\" suffix and"
                    . ' alias the import at the call sites that read better with it (see'
                    . ' docs/standards/classes-class-naming.md)',
                $stackPtr,
                'Found',
                [self::DECLARATION_KEYWORDS[$phpcsFile->getTokens()[$stackPtr]['code']], $name, $segment, $suffix]
            );

            return;
        }
    }

    private function segmentsBelowApplicationRoot(File $phpcsFile, int $stackPtr): array
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        if ($namespace === null) {
            return [];
        }

        $segments = explode('\\', $namespace);
        $root = array_shift($segments);

        return strtolower((string) $root) === self::APPLICATION_ROOT ? $segments : [];
    }

    private function redundantSuffix(string $name, string $segment): ?string
    {
        foreach ($this->suffixCandidates(strtolower($segment)) as $candidate) {
            $offset = strlen($name) - strlen($candidate);

            if (
                $offset < 0
                || strtolower(substr($name, $offset)) !== $candidate
            ) {
                continue;
            }

            // The whole name, or a PascalCase word of it. A match starting
            // mid-word is a letter collision rather than a repeated folder.
            if (
                $offset === 0
                || ctype_upper($name[$offset]) === true
            ) {
                return substr($name, $offset);
            }
        }

        return null;
    }

    private function suffixCandidates(string $segment): array
    {
        $candidates = [$segment, ...$this->singularForms($segment)];

        $candidates = array_unique(array_filter(
            $candidates,
            static fn (string $candidate): bool => $candidate !== ''
        ));

        usort($candidates, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return $candidates;
    }

    private function singularForms(string $segment): array
    {
        if (isset(self::IRREGULAR_PLURALS[$segment]) === true) {
            return [self::IRREGULAR_PLURALS[$segment]];
        }

        if (str_ends_with($segment, 's') === false) {
            return [];
        }

        $forms = [substr($segment, 0, -1)];

        if (str_ends_with($segment, 'es') === true) {
            $forms[] = substr($segment, 0, -2);
        }

        if (
            str_ends_with($segment, 'ies') === true
            && strlen($segment) > 3
        ) {
            $forms[] = substr($segment, 0, -3) . 'y';
        }

        return $forms;
    }
}

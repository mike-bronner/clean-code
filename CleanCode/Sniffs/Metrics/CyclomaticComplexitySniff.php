<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use MikeBronner\CleanCode\Support\CyclomaticComplexity;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class CyclomaticComplexitySniff implements Sniff
{
    private const DEFAULT_REPORT_LEVEL = 10;

    public int|string|null $reportLevel = self::DEFAULT_REPORT_LEVEL;

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $threshold = $this->threshold();
        $complexity = CyclomaticComplexity::forDeclaration($phpcsFile, $stackPtr);

        if ($complexity < $threshold) {
            return;
        }

        $phpcsFile->addError(
            'The %s has a cyclomatic complexity of %s, reaching the report level of %s; '
                . 'break it into smaller declarations '
                . '(see docs/phpmd/codesize-cyclomaticcomplexity.md)',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $complexity, $threshold]
        );
    }

    private function threshold(): int
    {
        $configured = trim((string) $this->reportLevel);

        if (
            preg_match('/^\d+$/', $configured) !== 1
            || (int) $configured < 1
        ) {
            return self::DEFAULT_REPORT_LEVEL;
        }

        return (int) $configured;
    }

    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $classLike = [T_ANON_CLASS, T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];
        $subject = 'function';

        foreach (array_reverse($conditions, true) as $code) {
            if (in_array($code, $classLike, true) === true) {
                $subject = 'method';

                break;
            }

            if (in_array($code, [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                break;
            }
        }

        return $subject . ' ' . $phpcsFile->getDeclarationName($stackPtr) . '()';
    }
}

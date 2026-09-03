<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class LongClassNameSniff implements Sniff
{
    public int $maximum = 40;

    public string $subtractPrefixes = '';

    public string $subtractSuffixes = '';

    public function register(): array
    {
        return [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        // A truncated declaration — a `class` keyword with no name after it —
        // has no length to measure, so say nothing rather than guess.
        if ($name === null) {
            return;
        }

        $length = $this->lengthWithoutPrefixesAndSuffixes($name);

        if ($length <= $this->maximum) {
            return;
        }

        $phpcsFile->addError(
            'Name %s is %s characters long; keep it to %s or fewer',
            $stackPtr,
            'TooLong',
            [$name, $length, $this->maximum]
        );
    }

    private function lengthWithoutPrefixesAndSuffixes(string $name): int
    {
        $length = strlen($name);

        foreach ($this->splitToList($this->subtractSuffixes) as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $length -= strlen($suffix);

                break;
            }
        }

        foreach ($this->splitToList($this->subtractPrefixes) as $prefix) {
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                $length -= strlen($prefix);

                break;
            }
        }

        return $length;
    }

    private function splitToList(string $value): array
    {
        return array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $entry): bool => $entry !== ''
        );
    }
}

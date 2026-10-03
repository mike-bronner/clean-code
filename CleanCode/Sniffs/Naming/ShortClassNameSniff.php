<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use MikeBronner\CleanCode\Helpers\Declarations;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ShortClassNameSniff implements Sniff
{
    public int $minimum = 3;

    public string $exceptions = '';

    public function register(): array
    {
        return [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $name = (new Declarations())->name($phpcsFile, $stackPtr);

        if (
            $name === null
            || strlen($name) >= $this->minimum
        ) {
            return;
        }

        if (in_array($name, $this->exceptionList(), true) === true) {
            return;
        }

        $phpcsFile->addError(
            'Avoid classes with short names like %s. Configured minimum length is %s.',
            $stackPtr,
            'TooShort',
            [$name, $this->minimum]
        );
    }

    private function exceptionList(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->exceptions)),
            static fn (string $exception): bool => $exception !== ''
        );
    }
}

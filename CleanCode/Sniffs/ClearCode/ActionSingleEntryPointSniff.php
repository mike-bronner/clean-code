<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class ActionSingleEntryPointSniff implements Sniff
{
    private const CLASS_NAME_SUFFIX = 'Action';

    private const NAMESPACE_SEGMENT = 'actions';

    private const CONSTRUCTOR = '__construct';

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isActionClass($phpcsFile, $stackPtr) === false) {
            return;
        }

        // The first entry point is the one the Action is entitled to; every
        // later one is a second concept sharing the class.
        $extras = array_slice($this->entryPoints($phpcsFile, $stackPtr), 1);

        foreach ($extras as $methodPtr) {
            $name = (string) $phpcsFile->getDeclarationName($methodPtr);

            $phpcsFile->addWarning(
                'Public method %s() is an additional entry point; an Action class exposes a'
                    . ' single public entry point, so move %s() into an Action class of its own'
                    . ' (see docs/standards/clear-code-encapsulate-related-methods-in-a-class.md)',
                $methodPtr,
                'Found',
                [$name, $name]
            );
        }
    }

    private function isActionClass(File $phpcsFile, int $stackPtr): bool
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name !== null
            && str_ends_with($name, self::CLASS_NAME_SUFFIX) === true
        ) {
            return true;
        }

        $segments = $this->namespaceSegments($phpcsFile, $stackPtr);

        return in_array(self::NAMESPACE_SEGMENT, $segments, true);
    }

    private function namespaceSegments(File $phpcsFile, int $stackPtr): array
    {
        $namespace = NamespaceHelper::findCurrentNamespaceName($phpcsFile, $stackPtr);

        return $namespace === null ? [] : array_map('strtolower', explode('\\', $namespace));
    }

    private function entryPoints(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $class = $tokens[$classPtr];

        if (isset($class['scope_opener'], $class['scope_closer']) === false) {
            return [];
        }

        $closer = $class['scope_closer'];
        $pointer = $class['scope_opener'] + 1;
        $pointers = [];

        while ($pointer < $closer) {
            $code = $tokens[$pointer]['code'];

            // A brace at the class's own top level opens a property hook or a
            // trait-adaptation block. Neither holds a method of this class.
            if ($code === T_OPEN_CURLY_BRACKET) {
                $pointer = ($tokens[$pointer]['bracket_closer'] ?? $closer) + 1;

                continue;
            }

            if ($code !== T_FUNCTION) {
                ++$pointer;

                continue;
            }

            $end = $this->endOfDeclaration($phpcsFile, $pointer, $closer);

            if ($end === null) {
                break;
            }

            if ($this->isEntryPoint($phpcsFile, $pointer) === true) {
                $pointers[] = $pointer;
            }

            $pointer = $end + 1;
        }

        return $pointers;
    }

    private function endOfDeclaration(File $phpcsFile, int $methodPtr, int $closer): ?int
    {
        $end = $phpcsFile->getTokens()[$methodPtr]['scope_closer']
            ?? $phpcsFile->findNext(T_SEMICOLON, $methodPtr + 1, $closer);

        return $end === false ? null : $end;
    }

    private function isEntryPoint(File $phpcsFile, int $methodPtr): bool
    {
        if ($phpcsFile->getMethodProperties($methodPtr)['scope'] !== 'public') {
            return false;
        }

        $name = $phpcsFile->getDeclarationName($methodPtr);

        return $name !== null && strtolower($name) !== self::CONSTRUCTOR;
    }
}

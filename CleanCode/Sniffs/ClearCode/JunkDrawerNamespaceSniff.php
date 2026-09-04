<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ClearCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class JunkDrawerNamespaceSniff implements Sniff
{
    public array $discouragedSegments = [
        'Helpers',
        'Utils',
        'Utilities',
        'Misc',
        'Common',
        'General',
    ];

    public function register(): array
    {
        return [T_NAMESPACE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        // T_NAMESPACE is also the `namespace\` relative-name operator
        // (`namespace\format()`), which carries no declaration to read. A
        // following T_NS_SEPARATOR is what tells the two apart.
        if (
            $namePtr === false
            || $tokens[$namePtr]['code'] === T_NS_SEPARATOR
        ) {
            return;
        }

        $segments = $this->nameSegments($phpcsFile, $namePtr);
        $discouraged = array_map('strtolower', $this->discouragedSegments);

        foreach ($segments as $segment) {
            if (in_array(strtolower($segment), $discouraged, true) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                "Namespace %s groups classes by technical role: the \"%s\" segment names a generic"
                    . ' bucket, not a real-world domain. Group related classes into a domain'
                    . ' namespace instead (see'
                    . ' docs/standards/clear-code-encapsulate-related-classes-in-a-domain.md)',
                $namePtr,
                'Found',
                [implode('\\', $segments), $segment]
            );
        }
    }

    private function nameSegments(File $phpcsFile, int $startPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = $startPtr; $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_SEMICOLON
                || $code === T_OPEN_CURLY_BRACKET
            ) {
                break;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $name .= $tokens[$i]['content'];
        }

        return explode('\\', $name);
    }
}

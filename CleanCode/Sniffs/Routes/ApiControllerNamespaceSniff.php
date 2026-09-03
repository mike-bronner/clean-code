<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ApiControllerNamespaceSniff implements Sniff
{
    private const CONTROLLERS_SEGMENT = 'controllers';

    private const API_SEGMENT = 'api';

    private const UNKNOWN_PATH = 'STDIN';

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($phpcsFile->getFilename() === self::UNKNOWN_PATH) {
            return;
        }

        $namespaceSegments = $this->namespaceSegments($phpcsFile, $stackPtr);
        $namespaceTail = $this->segmentsBelowControllerRoot($namespaceSegments);

        // A declared namespace carrying no controller root places the class
        // outside one, and that is the stronger statement: the path can pick up
        // a `Controllers` segment from the checkout location, the declared
        // namespace cannot.
        if (
            $namespaceTail === null
            && $namespaceSegments !== []
        ) {
            return;
        }

        $pathTail = $this->segmentsBelowControllerRoot($this->pathSegments($phpcsFile));

        // Neither the namespace nor the location says "controller", so this
        // class is none of the standard's business.
        if (
            $namespaceTail === null
            && $pathTail === null
        ) {
            return;
        }

        $namespaceIsApi = $namespaceTail !== null && in_array(self::API_SEGMENT, $namespaceTail, true);
        $pathIsApi = $pathTail !== null && in_array(self::API_SEGMENT, $pathTail, true);

        if ($namespaceIsApi === $pathIsApi) {
            return;
        }

        if ($pathIsApi === true) {
            $phpcsFile->addError(
                'A controller under an API path segment must be declared in a namespace with a'
                    . ' matching API segment, e.g. App\Http\Controllers\API',
                $stackPtr,
                'MissingApiNamespace'
            );

            return;
        }

        $phpcsFile->addError(
            'A controller declared in an API namespace must live under a matching API path'
                . ' segment; a view controller carries no API namespace segment',
            $stackPtr,
            'UnexpectedApiNamespace'
        );
    }

    private function segmentsBelowControllerRoot(array $segments): ?array
    {
        $lowered = array_map('strtolower', $segments);
        $roots = array_keys($lowered, self::CONTROLLERS_SEGMENT, true);

        return $roots === [] ? null : array_slice($lowered, (end($roots) + 1));
    }

    private function pathSegments(File $phpcsFile): array
    {
        $segments = explode('/', str_replace('\\', '/', $phpcsFile->getFilename()));

        array_pop($segments);

        return $segments;
    }

    private function namespaceSegments(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $searchFrom = $stackPtr;

        while (true) {
            $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, ($searchFrom - 1));

            if ($namespacePtr === false) {
                return [];
            }

            $searchFrom = $namespacePtr;
            $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

            if (
                $namePtr === false
                || $tokens[$namePtr]['code'] === T_NS_SEPARATOR
            ) {
                continue;
            }

            return $this->readName($phpcsFile, $namePtr);
        }
    }

    private function readName(File $phpcsFile, int $startPtr): array
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

        $name = trim($name, '\\');

        return $name === '' ? [] : explode('\\', $name);
    }
}

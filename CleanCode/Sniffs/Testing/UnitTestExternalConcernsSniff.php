<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class UnitTestExternalConcernsSniff implements Sniff
{
    private const UNKNOWN_PATH = 'STDIN';

    private const DATABASE_TRAITS = [
        'databasemigrations',
        'databasetransactions',
        'lazilyrefreshdatabase',
        'refreshdatabase',
    ];

    private const FAKEABLE_FACADES = [
        'bus',
        'event',
        'http',
        'mail',
        'notification',
        'queue',
        'storage',
    ];

    private const FAKE_METHOD = 'fake';

    private const HTTP_KERNEL_METHODS = [
        'delete',
        'deletejson',
        'get',
        'getjson',
        'patch',
        'patchjson',
        'post',
        'postjson',
        'put',
        'putjson',
    ];

    private const OBJECT_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
    ];

    public string $unitTestPath = 'tests/Unit/';

    public function register(): array
    {
        return [
            T_USE,
            T_DOUBLE_COLON,
            T_VARIABLE,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isUnitTestFile($phpcsFile) === false) {
            return;
        }

        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_USE) {
            $this->processUse($phpcsFile, $stackPtr);

            return;
        }

        if ($code === T_DOUBLE_COLON) {
            $this->processFacadeFake($phpcsFile, $stackPtr);

            return;
        }

        $this->processHttpRequest($phpcsFile, $stackPtr);
    }

    private function processUse(File $phpcsFile, int $stackPtr): void
    {
        foreach ($this->usedNames($phpcsFile, $stackPtr) as $namePtr => $name) {
            if (in_array(strtolower($this->trailingSegment($name)), self::DATABASE_TRAITS, true) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                'A unit test concerns only the class under test: %s stands the database up,'
                    . ' which belongs in a %s test',
                $namePtr,
                'DatabaseTrait',
                [
                    $this->trailingSegment($name),
                    $this->suiteSibling(),
                ]
            );
        }
    }

    private function processFacadeFake(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $methodPtr === false
            || strtolower($tokens[$methodPtr]['content']) !== self::FAKE_METHOD
        ) {
            return;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $facade = $this->trailingSegment($this->receiverBefore($phpcsFile, $stackPtr));

        if (in_array(strtolower($facade), self::FAKEABLE_FACADES, true) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'A unit test concerns only the class under test: %s::fake() doubles out an external'
                . ' subsystem, which belongs in a %s test',
            $stackPtr,
            'FacadeFake',
            [
                $facade,
                $this->suiteSibling(),
            ]
        );
    }

    private function processHttpRequest(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['content'] !== '$this') {
            return;
        }

        $operatorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $operatorPtr === false
            || in_array($tokens[$operatorPtr]['code'], self::OBJECT_OPERATORS, true) === false
        ) {
            return;
        }

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if (
            $methodPtr === false
            || $tokens[$methodPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $method = $tokens[$methodPtr]['content'];

        if (in_array(strtolower($method), self::HTTP_KERNEL_METHODS, true) === false) {
            return;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'A unit test concerns only the class under test: $this->%s() dispatches through the HTTP'
                . ' kernel, which belongs in a %s test',
            $methodPtr,
            'HttpRequest',
            [
                $method,
                $this->suiteSibling(),
            ]
        );
    }

    private function usedNames(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $current = '';
        $currentPtr = null;
        $skipMember = false;

        for ($i = ($stackPtr + 1); $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_OPEN_PARENTHESIS) {
                return [];
            }

            if ($code === T_OPEN_CURLY_BRACKET) {
                break;
            }

            if ($code === T_OPEN_USE_GROUP) {
                $current = '';
                $currentPtr = null;
                $skipMember = false;

                continue;
            }

            if (
                $code === T_CLOSE_USE_GROUP
                || $code === T_SEMICOLON
            ) {
                break;
            }

            if ($code === T_COMMA) {
                $names = $this->collect($names, $currentPtr, $current);
                $current = '';
                $currentPtr = null;
                $skipMember = false;

                continue;
            }

            if (
                isset(Tokens::$emptyTokens[$code]) === true
                || $skipMember === true
            ) {
                continue;
            }

            if ($code === T_AS) {
                $names = $this->collect($names, $currentPtr, $current);
                $current = '';
                $currentPtr = null;
                $skipMember = true;

                continue;
            }

            if (
                $currentPtr === null
                && $this->isKindMarker($tokens[$i]['content']) === true
            ) {
                $skipMember = true;

                continue;
            }

            $currentPtr ??= $i;
            $current .= $tokens[$i]['content'];
        }

        return $this->collect($names, $currentPtr, $current);
    }

    private function isKindMarker(string $content): bool
    {
        return in_array(strtolower($content), ['function', 'const'], true);
    }

    private function collect(array $names, ?int $pointer, string $name): array
    {
        if (
            $pointer === null
            || trim($name, '\\') === ''
        ) {
            return $names;
        }

        $names[$pointer] = $name;

        return $names;
    }

    private function receiverBefore(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_STRING
                || $code === T_NS_SEPARATOR
                || $code === T_NAME_QUALIFIED
                || $code === T_NAME_FULLY_QUALIFIED
            ) {
                $name = $tokens[$i]['content'] . $name;

                continue;
            }

            break;
        }

        return $name;
    }

    private function isUnitTestFile(File $phpcsFile): bool
    {
        if ($phpcsFile->getFilename() === self::UNKNOWN_PATH) {
            return false;
        }

        $scope = $this->pathSegments($this->unitTestPath);

        // An empty property names no directory, so it reaches no file. Stated
        // rather than left to fall out: the scan below happens to reach the
        // same answer, because a null root matches no segment, but that is an
        // accident of array_keys() rather than the decision this rule makes.
        // The alternative reading — an empty prefix matching every path —
        // would turn one blank <property> element into a warning on every file
        // in a consuming project.
        if ($scope === []) {
            return false;
        }

        $segments = $this->pathSegments($phpcsFile->getFilename());

        array_pop($segments);

        $root = array_shift($scope);
        $roots = array_keys($segments, $root, true);

        if ($roots === []) {
            return false;
        }

        return array_slice($segments, (end($roots) + 1), count($scope)) === $scope;
    }

    private function pathSegments(string $path): array
    {
        return array_map('strtolower', $this->splitPath($path));
    }

    private function splitPath(string $path): array
    {
        return array_values(
            array_filter(
                explode('/', str_replace('\\', '/', $path)),
                static fn (string $segment): bool => $segment !== ''
            )
        );
    }

    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', trim($name, '\\'));

        return (string) end($segments);
    }

    private function suiteSibling(): string
    {
        $segments = $this->splitPath($this->unitTestPath);

        array_pop($segments);

        $segments[] = 'Feature';

        return implode('/', $segments) . '/';
    }
}

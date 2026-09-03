<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoHttpFakesInIntegrationTestsSniff implements Sniff
{
    private const HTTP_FACADE = 'http';

    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public array $integrationPatterns = [
        '*/tests/Integration/*',
    ];

    public array $fakeMethods = [
        'fake',
        'fakeSequence',
        'preventStrayRequests',
    ];

    public array $mockCreators = [
        'createMock',
        'mock',
    ];

    public array $httpClientClasses = [
        'GuzzleHttp\Client',
        'Illuminate\Http\Client',
    ];

    public function register(): array
    {
        return [
            T_DOUBLE_COLON,
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isIntegrationTest($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $memberPtr === false
            || $tokens[$memberPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $openPtr = $this->callOpener($phpcsFile, $memberPtr);

        if ($openPtr === null) {
            return;
        }

        $member = $tokens[$memberPtr]['content'];

        if ($this->isHttpFake($phpcsFile, $stackPtr, $member) === true) {
            $phpcsFile->addWarning(
                'Http::%s() doubles out the external dependency this integration test exists to'
                    . ' exercise; keep the fake in the feature-test twin instead'
                    . ' (see docs/standards/testing-test-suites.md)',
                $memberPtr,
                'FakedHttpClient',
                [$member]
            );

            return;
        }

        if ($this->matches($member, $this->mockCreators) === false) {
            return;
        }

        $mocked = $this->mockedHttpClient($phpcsFile, $openPtr);

        if ($mocked === null) {
            return;
        }

        $phpcsFile->addWarning(
            'Mocking %s doubles out the external dependency this integration test exists to'
                . ' exercise; keep the double in the feature-test twin instead'
                . ' (see docs/standards/testing-test-suites.md)',
            $memberPtr,
            'MockedHttpClient',
            [$mocked]
        );
    }

    private function isIntegrationTest(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->integrationPatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    private function callOpener(File $phpcsFile, int $memberPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        $ellipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if (
            $ellipsisPtr === false
            || $tokens[$ellipsisPtr]['code'] !== T_ELLIPSIS
        ) {
            return $openPtr;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsisPtr + 1), null, true);
        $isCallable = $afterPtr !== false && $tokens[$afterPtr]['code'] === T_CLOSE_PARENTHESIS;

        return $isCallable === true ? null : $openPtr;
    }

    private function isHttpFake(File $phpcsFile, int $stackPtr, string $member): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        if ($this->matches($member, $this->fakeMethods) === false) {
            return false;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false) {
            return false;
        }

        if (in_array($tokens[$receiverPtr]['code'], self::NAME_TOKENS, true) === false) {
            return false;
        }

        return $this->trailingSegment($tokens[$receiverPtr]['content']) === self::HTTP_FACADE;
    }

    private function mockedHttpClient(File $phpcsFile, int $openPtr): ?string
    {
        // An empty argument list needs no guard of its own: the token found
        // here is then the closing parenthesis, which is neither a string
        // literal nor the start of a name, so classReference() rejects it.
        $argumentPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if ($argumentPtr === false) {
            return null;
        }

        $written = $this->classReference($phpcsFile, $argumentPtr);

        if ($written === null) {
            return null;
        }

        return $this->isHttpClient($written) === true ? $written : null;
    }

    private function classReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argumentPtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            // PHP never resolves a class name given as a string through the
            // file's imports, so the literal is already fully qualified
            // whatever it is written as. The leading separator is what says so
            // to isHttpClient().
            $written = '\\' . ltrim($this->literalValue($tokens[$argumentPtr]['content']), '\\');

            return $this->endsTheArgument($phpcsFile, ($argumentPtr + 1)) === true ? $written : null;
        }

        // An argument carrying no name at all needs no guard of its own: the
        // run is then empty and leaves the pointer where it started, and the
        // token there — a variable, an ellipsis, `self`, the closing
        // parenthesis — is not `::`, so classConstantEnd() rejects it.
        [$written, $pointer] = $this->nameRun($tokens, $argumentPtr);
        $pointer = $this->classConstantEnd($phpcsFile, $pointer);

        if ($pointer === null) {
            return null;
        }

        return $this->endsTheArgument($phpcsFile, $pointer) === true ? $written : null;
    }

    private function nameRun(array $tokens, int $pointer): array
    {
        $written = '';

        for (; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return [$written, $pointer];
    }

    private function classConstantEnd(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $doubleColonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if (
            $doubleColonPtr === false
            || $tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON
        ) {
            return null;
        }

        $constantPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), null, true);

        if (
            $constantPtr === false
            || strtolower($tokens[$constantPtr]['content']) !== 'class'
        ) {
            return null;
        }

        return ($constantPtr + 1);
    }

    private function endsTheArgument(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundaryPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($boundaryPtr === false) {
            return false;
        }

        return in_array($tokens[$boundaryPtr]['code'], [T_COMMA, T_CLOSE_PARENTHESIS], true);
    }

    private function literalValue(string $content): string
    {
        return str_replace('\\\\', '\\', trim($content, '\'"'));
    }

    private function isHttpClient(string $written): bool
    {
        $segments = array_map('strtolower', explode('\\', ltrim($written, '\\')));
        $isQualified = str_contains($written, '\\');

        foreach ($this->httpClientClasses as $client) {
            $clientSegments = array_map('strtolower', explode('\\', ltrim($client, '\\')));

            if ($isQualified === false) {
                if ($segments === [(string) end($clientSegments)]) {
                    return true;
                }

                continue;
            }

            if (array_slice($segments, 0, count($clientSegments)) === $clientSegments) {
                return true;
            }
        }

        return false;
    }

    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', $name);

        return strtolower((string) end($segments));
    }

    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }
}

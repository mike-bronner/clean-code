<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

class NoInternetTraversalSniff implements Sniff
{
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAMESPACE,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    private const HEREDOC_OPENERS = [
        T_START_HEREDOC,
        T_START_NOWDOC,
    ];

    private const HEREDOC_BODIES = [
        T_HEREDOC,
        T_NOWDOC,
    ];

    private const NETWORK_FUNCTIONS = [
        'curl_exec',
        'curl_init',
        'fsockopen',
        'stream_socket_client',
    ];

    private const URL_READER = 'file_get_contents';

    private const URL_PARAMETER = 'filename';

    private const NETWORK_SCHEMES = [
        'http://',
        'https://',
    ];

    private const NETWORK_CLIENTS = [
        'guzzlehttp\client',
    ];

    public array $featureTestPatterns = [
        '*/tests/Feature/*',
    ];

    public function __construct(
        private FunctionCalls $functionCalls = new FunctionCalls()
    ) {
    }

    public function register(): array
    {
        return [
            T_NEW,
            T_STRING,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isFeatureTest($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_NEW) {
            $this->processInstantiation($phpcsFile, $stackPtr);

            return;
        }

        $this->processCall($phpcsFile, $stackPtr);
    }

    private function isFeatureTest(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->featureTestPatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    private function processCall(File $phpcsFile, int $stackPtr): void
    {
        $functionCalls = $this->functionCalls;

        $tokens = $phpcsFile->getTokens();
        $name = strtolower($tokens[$stackPtr]['content']);
        $isNetworkFunction = in_array($name, self::NETWORK_FUNCTIONS, true);

        if (
            $isNetworkFunction === false
            && $name !== self::URL_READER
        ) {
            return;
        }

        if ($functionCalls->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->isFirstClassCallable($phpcsFile, $stackPtr) === true) {
            return;
        }

        if ($isNetworkFunction === false) {
            if ($this->readsNetworkUrl($phpcsFile, $stackPtr) === false) {
                return;
            }
        }

        $this->report($phpcsFile, $stackPtr, $tokens[$stackPtr]['content'] . '()');
    }

    private function isFirstClassCallable(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($openPtr === false) {
            return false;
        }

        $ellipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if (
            $ellipsisPtr === false
            || $tokens[$ellipsisPtr]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsisPtr + 1), null, true);

        return $afterPtr !== false && $tokens[$afterPtr]['code'] === T_CLOSE_PARENTHESIS;
    }

    private function readsNetworkUrl(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $openPtr === false
            || isset($tokens[$openPtr]['parenthesis_closer']) === false
        ) {
            return false;
        }

        $closePtr = $tokens[$openPtr]['parenthesis_closer'];
        $urlPtr = $this->urlArgument($phpcsFile, $openPtr, $closePtr);

        if ($urlPtr === null) {
            return false;
        }

        $url = $this->wholeLiteral($phpcsFile, $urlPtr, $closePtr);

        return $url !== null && $this->namesNetworkScheme($url);
    }

    private function wholeLiteral(File $phpcsFile, int $urlPtr, int $closePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array($tokens[$urlPtr]['code'], self::HEREDOC_OPENERS, true) === true) {
            return $this->wholeHeredoc($phpcsFile, $urlPtr, $closePtr);
        }

        if (in_array($tokens[$urlPtr]['code'], self::STRING_TOKENS, true) === false) {
            return null;
        }

        return $this->endsArgument($phpcsFile, $urlPtr, $closePtr) === true
            ? (new StringLiteral())->inner($tokens[$urlPtr]['content'])
            : null;
    }

    private function wholeHeredoc(File $phpcsFile, int $openerPtr, int $closePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $closerPtr = $tokens[$openerPtr]['scope_closer'] ?? null;

        if ($closerPtr !== ($openerPtr + 2)) {
            return null;
        }

        $bodyIsText = in_array($tokens[$openerPtr + 1]['code'], self::HEREDOC_BODIES, true);

        if (
            $bodyIsText === false
            || $this->endsArgument($phpcsFile, $closerPtr, $closePtr) === false
        ) {
            return null;
        }

        $marker = $tokens[$closerPtr]['content'];
        $indent = substr($marker, 0, strspn($marker, " \t"));
        $body = rtrim($tokens[$openerPtr + 1]['content'], "\r\n");

        return $indent !== '' && str_starts_with($body, $indent)
            ? substr($body, strlen($indent))
            : $body;
    }

    private function endsArgument(File $phpcsFile, int $endPtr, int $closePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $afterPtr = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($endPtr + 1),
            ($closePtr + 1),
            true
        );

        return $afterPtr === $closePtr
            || ($afterPtr !== false && $tokens[$afterPtr]['code'] === T_COMMA);
    }

    private function urlArgument(File $phpcsFile, int $openPtr, int $closePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $labelPtr = $this->urlParameterLabel($phpcsFile, $openPtr, $closePtr);

        if ($labelPtr !== null) {
            return $this->labelledValue($phpcsFile, $labelPtr, $closePtr);
        }

        $firstPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), $closePtr, true);

        if (
            $firstPtr === false
            || $tokens[$firstPtr]['code'] === T_PARAM_NAME
        ) {
            return null;
        }

        return $firstPtr;
    }

    private function urlParameterLabel(File $phpcsFile, int $openPtr, int $closePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($pointer = ($openPtr + 1); $pointer < $closePtr; $pointer++) {
            $isOwnLabel = $tokens[$pointer]['code'] === T_PARAM_NAME
                && $tokens[$pointer]['content'] === self::URL_PARAMETER
                && array_key_last($tokens[$pointer]['nested_parenthesis'] ?? []) === $openPtr;

            if ($isOwnLabel === true) {
                return $pointer;
            }
        }

        return null;
    }

    private function labelledValue(File $phpcsFile, int $labelPtr, int $closePtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $colonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($labelPtr + 1), $closePtr, true);

        if (
            $colonPtr === false
            || $tokens[$colonPtr]['code'] !== T_COLON
        ) {
            return null;
        }

        $valuePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($colonPtr + 1), $closePtr, true);

        return $valuePtr === false ? null : $valuePtr;
    }

    private function namesNetworkScheme(string $url): bool
    {
        $lowered = strtolower($url);

        foreach (self::NETWORK_SCHEMES as $scheme) {
            if (str_starts_with($lowered, $scheme) === true) {
                return true;
            }
        }

        return false;
    }

    private function processInstantiation(File $phpcsFile, int $stackPtr): void
    {
        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($namePtr === false) {
            return;
        }

        $written = $this->writtenName($phpcsFile, $namePtr);

        if ($written === null) {
            return;
        }

        $resolved = ltrim(NamespaceHelper::resolveClassName($phpcsFile, $written, $namePtr), '\\');

        if (in_array(strtolower($resolved), self::NETWORK_CLIENTS, true) === false) {
            return;
        }

        $this->report($phpcsFile, $namePtr, "new {$resolved}");
    }

    private function writtenName(File $phpcsFile, int $namePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $written = '';

        for ($pointer = $namePtr; $pointer < $phpcsFile->numTokens; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return $written === '' ? null : $written;
    }

    private function report(File $phpcsFile, int $stackPtr, string $primitive): void
    {
        $phpcsFile->addWarning(
            '%s traverses the internet; a feature test must not, so fake the third-party API'
                . ' through the Http facade instead (see docs/standards/testing-test-suites.md)',
            $stackPtr,
            'Found',
            [$primitive]
        );
    }
}

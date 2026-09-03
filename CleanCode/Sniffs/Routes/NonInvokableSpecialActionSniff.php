<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NonInvokableSpecialActionSniff implements Sniff
{
    private const ROUTE_FACADE = 'Route';

    private const ACTION_PARAMETER = 'action';

    private const ACTION_POSITIONS = [
        'get' => 2,
        'post' => 2,
        'put' => 2,
        'patch' => 2,
        'delete' => 2,
        'options' => 2,
        'any' => 2,
        'match' => 3,
    ];

    private const RESTFUL_ACTIONS = [
        'index',
        'create',
        'store',
        'show',
        'edit',
        'update',
        'destroy',
    ];

    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    private const CLOSER_KEYS = [
        'parenthesis_closer',
        'bracket_closer',
        'attribute_closer',
        'scope_closer',
    ];

    private const ARROW_FUNCTION_TOKENS = [
        T_FN,
        T_FN_ARROW,
    ];

    private const SINGLE_QUOTED_ESCAPES = [
        '\\' => '\\',
        "'" => "'",
    ];

    private const DOUBLE_QUOTED_ESCAPES = [
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'v' => "\v",
        'e' => "\e",
        'f' => "\f",
        '\\' => '\\',
        '$' => '$',
        "\"" => "\"",
    ];

    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    private const CLASS_NAME_PATTERN
        = '/^\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*$/';

    public array $routeFilePatterns = [
        '*/routes/*',
    ];

    public function register(): array
    {
        return [T_DOUBLE_COLON];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isRouteFile($phpcsFile->getFilename()) === false) {
            return;
        }

        $registration = $this->routeRegistration($phpcsFile, $stackPtr);

        if ($registration === null) {
            return;
        }

        [$verbPtr, $position] = $registration;
        $argument = $this->actionArgument($phpcsFile, $verbPtr, $position);

        if ($argument === null) {
            return;
        }

        [$start, $end] = $argument;
        $method = $this->targetMethod($phpcsFile, $start, $end);

        if (
            $method === null
            || in_array($method, self::RESTFUL_ACTIONS, true) === true
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'A special action route should point to an invokable controller; this one targets'
                . ' %s() on a shared controller (see'
                . ' docs/standards/routes-conventions-do-do-not.md)',
            $start,
            'Found',
            [$method]
        );
    }

    private function isRouteFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->routeFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    private function routeRegistration(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $receiverPtr === false
            || $tokens[$receiverPtr]['code'] !== T_STRING
        ) {
            return null;
        }

        if ($tokens[$receiverPtr]['content'] !== self::ROUTE_FACADE) {
            return null;
        }

        $verbPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $verbPtr === false
            || $tokens[$verbPtr]['code'] !== T_STRING
        ) {
            return null;
        }

        $position = self::ACTION_POSITIONS[strtolower($tokens[$verbPtr]['content'])] ?? null;

        return $position === null ? null : [$verbPtr, $position];
    }

    private function actionArgument(File $phpcsFile, int $verbPtr, int $position): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($verbPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        $arguments = array_map(
            fn (array $range): array => $this->labelledArgument($phpcsFile, $range[0], $range[1]),
            $this->argumentRanges($phpcsFile, $openPtr)
        );

        foreach ($arguments as $argument) {
            if ($argument['label'] === self::ACTION_PARAMETER) {
                return [$argument['start'], $argument['end']];
            }
        }

        $positional = array_values(
            array_filter($arguments, static fn (array $argument): bool => $argument['label'] === null)
        );
        $action = $positional[$position - 1] ?? null;

        return $action === null ? null : [$action['start'], $action['end']];
    }

    private function argumentRanges(File $phpcsFile, int $openPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $closePtr = $tokens[$openPtr]['parenthesis_closer'] ?? null;

        return $closePtr === null ? [] : $this->rangesUntil($phpcsFile, $openPtr, $closePtr);
    }

    private function labelledArgument(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_PARAM_NAME) {
            return ['label' => null, 'start' => $start, 'end' => $end];
        }

        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        return [
            'label' => $tokens[$start]['content'],
            'start' => $meaningful[2] ?? $end,
            'end' => $end,
        ];
    }

    private function groupCloser(array $tokens, int $ptr): int
    {
        $isArrowFunction = in_array($tokens[$ptr]['code'], self::ARROW_FUNCTION_TOKENS, true);

        foreach (self::CLOSER_KEYS as $key) {
            if (
                $key === 'scope_closer'
                && $isArrowFunction === true
            ) {
                continue;
            }

            if (
                isset($tokens[$ptr][$key]) === true
                && $tokens[$ptr][$key] > $ptr
            ) {
                return $tokens[$ptr][$key];
            }
        }

        return $ptr;
    }

    private function targetMethod(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$start]['code'] === T_OPEN_SHORT_ARRAY
            || $tokens[$start]['code'] === T_ARRAY
        ) {
            return $this->methodFromArrayAction($phpcsFile, $start, $end);
        }

        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        if (
            count($meaningful) !== 1
            || $tokens[$start]['code'] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            return null;
        }

        return $this->methodFromStringAction($this->literalValue($tokens[$start]['content']));
    }

    private function methodFromArrayAction(File $phpcsFile, int $start, int $end): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $tokens[$start]['code'] === T_ARRAY
            ? ($tokens[$start]['parenthesis_opener'] ?? null)
            : $start;

        if (
            $openPtr === null
            || $this->groupCloser($tokens, $openPtr) !== $end
        ) {
            return null;
        }

        $elements = $this->rangesUntil($phpcsFile, $openPtr, $end);

        if (count($elements) !== 2) {
            return null;
        }

        if ($this->isClassConstant($phpcsFile, $elements[0][0], $elements[0][1]) === false) {
            return null;
        }

        [$methodStart, $methodEnd] = $elements[1];
        $meaningful = $this->meaningfulTokens($phpcsFile, $methodStart, $methodEnd);

        if (
            count($meaningful) !== 1
            || $tokens[$methodStart]['code'] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            return null;
        }

        $method = $this->literalValue($tokens[$methodStart]['content']);

        return preg_match(self::IDENTIFIER_PATTERN, $method) === 1 ? $method : null;
    }

    private function rangesUntil(File $phpcsFile, int $openPtr, int $closePtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $ranges = [];
        $start = null;
        $end = null;

        for ($ptr = ($openPtr + 1); $ptr < $closePtr; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_COMMA) {
                if ($start !== null) {
                    $ranges[] = [$start, $end];
                }

                $start = null;

                continue;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $end = $this->groupCloser($tokens, $ptr);
            $start ??= $ptr;
            $ptr = $end;
        }

        if ($start !== null) {
            $ranges[] = [$start, $end];
        }

        return $ranges;
    }

    private function isClassConstant(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $meaningful = $this->meaningfulTokens($phpcsFile, $start, $end);

        if (count($meaningful) < 3) {
            return false;
        }

        $keywordPtr = (int) array_pop($meaningful);
        $operatorPtr = (int) array_pop($meaningful);

        if ($tokens[$operatorPtr]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        if (strtolower($tokens[$keywordPtr]['content']) !== 'class') {
            return false;
        }

        foreach ($meaningful as $ptr) {
            if (in_array($tokens[$ptr]['code'], self::NAME_TOKENS, true) === false) {
                return false;
            }
        }

        return true;
    }

    private function methodFromStringAction(string $action): ?string
    {
        if (substr_count($action, '@') !== 1) {
            return null;
        }

        [$controller, $method] = explode('@', $action);

        if (preg_match(self::CLASS_NAME_PATTERN, $controller) !== 1) {
            return null;
        }

        return preg_match(self::IDENTIFIER_PATTERN, $method) === 1 ? $method : null;
    }

    private function literalValue(string $content): string
    {
        $body = substr($content, 1, -1);

        // Both reads fall back to the body as the source spells it. A failed
        // read cast to a string is '', and an empty action name matches
        // nothing, so the route would be skipped with the sniff silent — the
        // failure has to leave a name behind, and the unevaluated one is the
        // closest true thing available. Neither pattern is known to be
        // drivable there: the first has no quantifier at all, the second only
        // the counted `{1,2}` and `{1,3}`, and neither carries a `/u` modifier.
        if ($content[0] === "'") {
            return preg_replace_callback(
                '/\\\\(.)/s',
                static fn (array $match): string => self::SINGLE_QUOTED_ESCAPES[$match[1]] ?? $match[0],
                $body
            ) ?? $body;
        }

        return preg_replace_callback(
            '/\\\\([xX][0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s',
            fn (array $match): string => $this->unescaped($match[1], $match[0]),
            $body
        ) ?? $body;
    }

    private function unescaped(string $sequence, string $written): string
    {
        if (preg_match('/^[xX][0-9A-Fa-f]{1,2}$/', $sequence) === 1) {
            return chr((int) hexdec(substr($sequence, 1)));
        }

        if (preg_match('/^[0-7]{1,3}$/', $sequence) === 1) {
            return chr((int) octdec($sequence) % 256);
        }

        return self::DOUBLE_QUOTED_ESCAPES[$sequence] ?? $written;
    }

    private function meaningfulTokens(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            if (isset(Tokens::$emptyTokens[$tokens[$ptr]['code']]) === true) {
                continue;
            }

            $pointers[] = $ptr;
        }

        return $pointers;
    }
}

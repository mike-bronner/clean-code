<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Pattern;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class AvoidDuplicateCodeBlocksSniff implements Sniff
{
    private const NON_CODE_TOKENS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_INLINE_HTML];

    private const OPEN_TAGS = [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO];

    private const DELIMITER_TOKENS = [
        T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET,
        T_OPEN_PARENTHESIS, T_CLOSE_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET, T_CLOSE_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY, T_CLOSE_SHORT_ARRAY,
        T_COMMA, T_SEMICOLON,
    ];

    public $minimumLines = 5;

    public function register(): array
    {
        return self::OPEN_TAGS;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($phpcsFile->findPrevious(self::OPEN_TAGS, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $minimumLines = max(1, (int) $this->minimumLines);
        [$lineShapes, $anchors] = $this->summarizeCodeLines($tokens);

        foreach ($this->findRepeatedBlocks($lineShapes, $minimumLines) as $block) {
            foreach ($block['starts'] as $position => $start) {
                $others = $block['starts'];
                unset($others[$position]);

                $phpcsFile->addWarning(
                    'This block of code, through line %d, is near-identical to %s, ignoring'
                        . ' variable, literal, and identifier names. Extract the shared logic'
                        . ' once the duplication has earned an abstraction (see'
                        . ' docs/standards/pattern-dont-repeat-yourself-dry.md).',
                    $anchors[$start],
                    'Found',
                    [
                        $tokens[$anchors[($start + $block['length']) - 1]]['line'],
                        $this->describeBlocks($tokens, $anchors, $others),
                    ]
                );
            }
        }
    }

    private function describeBlocks(array $tokens, array $anchors, array $starts): string
    {
        $lines = [];

        foreach ($starts as $start) {
            $lines[] = $tokens[$anchors[$start]]['line'];
        }

        $last = array_pop($lines);

        if ($lines === []) {
            return "the block starting on line {$last}";
        }

        return 'the blocks starting on lines ' . implode(', ', $lines) . ' and ' . $last;
    }

    private function summarizeCodeLines(array $tokens): array
    {
        $ignored = Tokens::$emptyTokens + array_fill_keys(self::NON_CODE_TOKENS, true);
        $delimiters = array_fill_keys(self::DELIMITER_TOKENS, true);
        $summaries = [];
        $line = null;

        foreach ($tokens as $pointer => $token) {
            if (isset($ignored[$token['code']]) === true) {
                continue;
            }

            if ($token['line'] !== $line) {
                $line = $token['line'];
                $summaries[$line] = ['anchor' => $pointer, 'shape' => '', 'code' => false];
            }

            $summaries[$line]['shape'] .= "{$token['code']},";
            $summaries[$line]['code'] = $summaries[$line]['code']
                || isset($delimiters[$token['code']]) === false;
        }

        $shapes = [];
        $lineShapes = [];
        $anchors = [];

        foreach ($summaries as $summary) {
            if ($summary['code'] === false) {
                continue;
            }

            $lineShapes[] = $shapes[$summary['shape']] ??= count($shapes);
            $anchors[] = $summary['anchor'];
        }

        return [$lineShapes, $anchors];
    }

    private function findRepeatedBlocks(array $lineShapes, int $minimumLines): array
    {
        $groups = [];
        $firstSeenAt = [];
        $windowCount = (count($lineShapes) - $minimumLines) + 1;

        for ($index = 0; $index < $windowCount; $index++) {
            $window = implode(',', array_slice($lineShapes, $index, $minimumLines));

            if (isset($firstSeenAt[$window]) === false) {
                $firstSeenAt[$window] = $index;

                continue;
            }

            $origin = $firstSeenAt[$window];

            if (($index - $origin) < $minimumLines) {
                continue;
            }

            $length = $this->measureBlock($lineShapes, $index, $origin, $minimumLines);
            $groups[$window] ??= ['starts' => [$origin], 'length' => $length];
            $groups[$window]['starts'][] = $index;
            $groups[$window]['length'] = min($groups[$window]['length'], $length);
            $index += ($length - 1);
        }

        return array_values($groups);
    }

    private function measureBlock(
        array $lineShapes,
        int $index,
        int $origin,
        int $minimumLines
    ): int {
        $length = $minimumLines;

        while (
            isset($lineShapes[$index + $length], $lineShapes[$origin + $length]) === true
            && $lineShapes[$index + $length] === $lineShapes[$origin + $length]
            && ($index - $origin) > $length
        ) {
            $length++;
        }

        return $length;
    }
}

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

    private const INTERPOLATING_TOKENS = [T_DOUBLE_QUOTED_STRING, T_HEREDOC];

    private const INTERPOLATED_VARIABLE = '/(?<!\\\\)((?:\\\\\\\\)*)'
        . '\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*/';

    public $minimumLines = 5;

    public $minimumTokens = 70;

    public function register(): array
    {
        return self::OPEN_TAGS;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ($phpcsFile->findPrevious(self::OPEN_TAGS, ($stackPtr - 1)) !== false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $minimumLines = max(1, (int) $this->minimumLines);
        [$lineShapes, $anchors, $lineTokens] = $this->summarizeCodeLines($phpcsFile);

        foreach ($this->findRepeatedBlocks($lineShapes, $minimumLines) as $block) {
            $blockTokens = array_sum(
                    array_slice($lineTokens, $block['starts'][0], $block['length'])
                );

            if ($blockTokens < (int) $this->minimumTokens) {
                continue;
            }

            foreach ($block['starts'] as $position => $start) {
                $others = $block['starts'];
                unset($others[$position]);

                $phpcsFile->addWarning(
                        'This block of code, through line %d, repeats %s token for token, apart'
                            . ' from variable names. Extract the shared logic once the duplication'
                            . ' has earned an abstraction (see'
                            . ' resources/boost/guidelines/pattern-dont-repeat-yourself-dry.md).',
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

    private function summarizeCodeLines(File $phpcsFile): array
    {
        $ignored = Tokens::$emptyTokens + array_fill_keys(self::NON_CODE_TOKENS, true);
        $summaries = [];

        foreach ($phpcsFile->getTokens() as $pointer => $token) {
            if (isset($ignored[$token['code']]) === true) {
                continue;
            }

            $summaries[$token['line']] ??= ['anchor' => $pointer, 'shape' => '', 'tokens' => 0];
            $summaries[$token['line']]['shape'] .= $this->signatureOf($token);
            $summaries[$token['line']]['tokens']++;
        }

        $shapes = [];
        $lineShapes = [];

        foreach ($summaries as $summary) {
            $lineShapes[] = $shapes[$summary['shape']] ??= count($shapes);
        }

        return [
            $lineShapes,
            array_column($summaries, 'anchor'),
            array_column($summaries, 'tokens'),
        ];
    }

    private function signatureOf(array $token): string
    {
        $content = match (true) {
            $token['code'] === T_VARIABLE => '$',
            in_array($token['code'], self::INTERPOLATING_TOKENS, true) => preg_replace(
                    self::INTERPOLATED_VARIABLE,
                    '$1$',
                    $token['content']
                ),
            default => $token['content'],
        };

        return $token['code'] . ':' . strlen($content) . ':' . $content;
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

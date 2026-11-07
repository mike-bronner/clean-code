<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class BladeDirectiveBlankLinesSniff implements Sniff
{
    private const MESSAGE_BEFORE = 'Expected a blank line before Blade directive @%s; found none';

    private const MESSAGE_AFTER = 'Expected a blank line after Blade directive @%s; found none';

    private const MASKED_REGIONS = '/(?<!@)@(verbatim|php)\b(.*?)@end\1'
        . '|\{\{--.*?--\}\}|\{!!.*?!!\}|\{\{.*?\}\}/s';

    private const CLOSER_PREFIX = 'end';

    private const DIRECTIVE = '/(?<![\w@])@(\w+)/';

    private const OPENER_STACKS = [
        'hassection' => 'if',
        'sectionmissing' => 'if',
    ];

    private const SECTION_CLOSERS = [
        'show',
        'stop',
        'append',
        'overwrite',
    ];

    private array $openerLines = [];

    private array $closerLines = [];

    public function register(): array
    {
        return [T_INLINE_HTML];
    }

    // phpcs:ignore CleanCode.DeadCode.UnusedFormalParameter
    public function process(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $rawLines = $this->lines($phpcsFile->getTokensAsString(0, $phpcsFile->numTokens));
        $firstTokenOnLine = $this->mapFirstTokenPerLine($tokens);
        $maskedLines = $this->maskedLines($tokens);

        if ($maskedLines === null) {
            return $phpcsFile->numTokens;
        }

        $blocks = $this->blocks($maskedLines, $rawLines);
        $this->mapBlockLines($blocks);

        foreach ($blocks as [$opener, $closer]) {
            $this->checkOpener($phpcsFile, $rawLines, $firstTokenOnLine, [$opener, $closer]);
            $this->checkCloser($phpcsFile, $rawLines, $firstTokenOnLine, [$opener, $closer]);
        }

        return $phpcsFile->numTokens;
    }

    private function lines(string $text): array
    {
        $lines = explode("\n", $text);

        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function mapFirstTokenPerLine(array $tokens): array
    {
        $firstTokenOnLine = [];

        foreach ($tokens as $pointer => $token) {
            $firstTokenOnLine[$token['line']] ??= $pointer;
        }

        return $firstTokenOnLine;
    }

    private function maskedLines(array $tokens): ?array
    {
        $markup = '';

        foreach ($tokens as $token) {
            $content = $token['content'];
            $markup .= ($token['code'] === T_INLINE_HTML ? $content : $this->blank($content));
        }

        $masked = preg_replace_callback(
                self::MASKED_REGIONS,
                fn (array $match): string => $this->maskRegion($match),
                $markup
            );

        if ($masked === null) {
            return null;
        }

        return $this->lines($masked);
    }

    private function maskRegion(array $match): string
    {
        if (($match[1] ?? '') === '') {
            return $this->blank($match[0]);
        }

        return "@{$match[1]}{$this->blank($match[2])}@end{$match[1]}";
    }

    private function blank(string $text): string
    {
        return preg_replace('/[^\r\n]/', ' ', $text) ?? $text;
    }

    private function blocks(array $lines, array $rawLines): array
    {
        $open = [];
        $blocks = [];

        foreach ($this->directives($lines, $rawLines) as $directive) {
            $closes = $this->closedStack($directive['name']);

            if ($closes === null) {
                $open[$this->openerStack($directive['name'])][] = $directive;

                continue;
            }

            if (($open[$closes] ?? []) === []) {
                continue;
            }

            $blocks[] = [array_pop($open[$closes]), $directive];
        }

        return $blocks;
    }

    private function mapBlockLines(array $blocks): void
    {
        $this->openerLines = [];
        $this->closerLines = [];

        foreach ($blocks as [$opener, $closer]) {
            if ($opener['startsLine'] === true) {
                $this->openerLines[$opener['line']] = $closer['line'];
            }

            if ($closer['startsLine'] === true) {
                $this->closerLines[$closer['line']] = $opener['line'];
            }
        }
    }

    private function isFirstChild(array $block): bool
    {
        [$opener] = $block;
        $parentCloser = ($this->openerLines[($opener['line'] - 1)] ?? 0);

        return $parentCloser > $opener['line'];
    }

    private function isLastChild(array $block): bool
    {
        [$opener, $closer] = $block;
        $parentOpener = ($this->closerLines[($closer['line'] + 1)] ?? PHP_INT_MAX);

        return $parentOpener < $opener['line'];
    }

    private function directives(array $lines, array $rawLines): array
    {
        $directives = [];

        foreach ($lines as $index => $line) {
            preg_match_all(self::DIRECTIVE, $line, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$name, $offset]) {
                $directives[] = [
                    'name' => $name,
                    'line' => ($index + 1),
                    'startsLine' => (trim(substr($rawLines[$index], 0, ($offset - 1))) === ''),
                ];
            }
        }

        return $directives;
    }

    private function closedStack(string $name): ?string
    {
        $name = strtolower($name);

        if (in_array($name, self::SECTION_CLOSERS, true) === true) {
            return 'section';
        }

        if (str_starts_with($name, self::CLOSER_PREFIX) === false) {
            return null;
        }

        return substr($name, strlen(self::CLOSER_PREFIX));
    }

    private function openerStack(string $name): string
    {
        $name = strtolower($name);

        return self::OPENER_STACKS[$name] ?? $name;
    }

    private function checkOpener(
        File $phpcsFile,
        array $rawLines,
        array $firstTokenOnLine,
        array $block
    ): void {
        [$opener] = $block;
        $line = $opener['line'];

        if (
            $opener['startsLine'] === false
            || $this->isBlank($rawLines, ($line - 1)) === true
            || $this->isFirstChild($block) === true
        ) {
            return;
        }

        $this->report(
                $phpcsFile,
                [self::MESSAGE_BEFORE, 'MissingBlankLineBefore', $opener['name']],
                $firstTokenOnLine[$line],
                $firstTokenOnLine[$line]
            );
    }

    private function checkCloser(
        File $phpcsFile,
        array $rawLines,
        array $firstTokenOnLine,
        array $block
    ): void {
        [, $closer] = $block;
        $line = $closer['line'];

        if (
            $closer['startsLine'] === false
            || $this->isBlank($rawLines, ($line + 1)) === true
            || $this->isLastChild($block) === true
        ) {
            return;
        }

        $this->report(
                $phpcsFile,
                [self::MESSAGE_AFTER, 'MissingBlankLineAfter', $closer['name']],
                $firstTokenOnLine[$line],
                $firstTokenOnLine[($line + 1)]
            );
    }

    private function isBlank(array $rawLines, int $line): bool
    {
        return trim($rawLines[($line - 1)] ?? '') === '';
    }

    private function report(
        File $phpcsFile,
        array $violation,
        int $pointer,
        int $insertBefore
    ): void {
        [$message, $code, $name] = $violation;
        $fix = $phpcsFile->addFixableError($message, $pointer, $code, [$name]);

        if ($fix === false) {
            return;
        }

        $fixer = $phpcsFile->fixer;
        $fixer->addContentBefore($insertBefore, $phpcsFile->eolChar);
    }
}

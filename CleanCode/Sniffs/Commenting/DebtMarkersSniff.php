<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DebtMarkersSniff implements Sniff
{
    private const MARKERS = [
        'HACK' => 'Hack',
        'XXX' => 'Xxx',
    ];

    private const TRIMMED_FROM_TASK = '-:[](). ';

    public function register(): array
    {
        return array_diff(Tokens::$commentTokens, Tokens::$phpcsCommentTokens);
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        foreach (self::MARKERS as $marker => $codePrefix) {
            $task = $this->markerTask($tokens[$stackPtr]['content'], $marker);

            if ($task === null) {
                continue;
            }

            if ($task === '') {
                $phpcsFile->addWarning(
                    'Comment refers to a %s task',
                    $stackPtr,
                    "{$codePrefix}CommentFound",
                    [$marker]
                );

                continue;
            }

            $phpcsFile->addWarning(
                "Comment refers to a %s task \"%s\"",
                $stackPtr,
                "{$codePrefix}TaskFound",
                [$marker, $task]
            );
        }
    }

    private function markerTask(string $content, string $marker): ?string
    {
        $matches = [];
        $pattern = '/(?:\A|[^\p{L}]+)' . $marker . '([^\p{L}]+(.*)|\Z)/ui';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return trim(trim($matches[1]), self::TRIMMED_FROM_TASK);
    }
}

<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\CodeStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class NoFormatterDirectivesSniff implements Sniff
{
    public array $directives = [
        '@formatter:off',
        '@formatter:on',
        'prettier-ignore',
    ];

    public function register(): array
    {
        return [
            T_COMMENT,
            T_DOC_COMMENT_TAG,
            T_DOC_COMMENT_STRING,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $directive = $this->directiveIn($tokens[$stackPtr]['content']);

        if ($directive === null) {
            return;
        }

        $phpcsFile->addError(
            'Auto-formatter directive %s must not be committed; correct style by hand instead',
            $stackPtr,
            'Found',
            [$directive]
        );
    }

    private function directiveIn(string $content): ?string
    {
        foreach ($this->directives as $directive) {
            $needle = trim($directive);

            if ($needle === '') {
                continue;
            }

            if (stripos($content, $needle) !== false) {
                return $needle;
            }
        }

        return null;
    }
}

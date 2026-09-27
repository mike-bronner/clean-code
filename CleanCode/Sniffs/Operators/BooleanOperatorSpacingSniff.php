<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

class BooleanOperatorSpacingSniff extends OperatorSpacingSniff
{
    public $ignoreNewlines = true;

    public function register(): array
    {
        parent::register();

        return Tokens::$booleanOperators;
    }
}

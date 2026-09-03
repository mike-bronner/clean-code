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
        // Called for its side effect: the parent builds its internal
        // non-operand token map here rather than in a constructor. The
        // returned target list is deliberately discarded — it is the parent's
        // own operator set, which this sniff replaces wholesale.
        //
        // Today that map changes nothing here, and the honest statement is
        // that removing this line breaks no test: the parent reads it only
        // while deciding whether a T_MINUS/T_PLUS is a unary sign, and this
        // sniff registers neither. It is kept because leaving an inherited
        // initialiser uncalled makes correctness depend on which of the
        // parent's private fields its process() happens to touch for our
        // tokens — a detail no test of ours pins and a PHP_CodeSniffer
        // upgrade may change.
        parent::register();

        return Tokens::$booleanOperators;
    }
}

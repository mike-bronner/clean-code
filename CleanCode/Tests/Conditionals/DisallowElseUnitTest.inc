<?php

// Compliant: guard clauses and early returns produce no violations.
function guardClauses(int $value): string
{
    if ($value < 0) {
        return 'negative';
    }

    if ($value === 0) {
        return 'zero';
    }

    return 'positive';
}

// Violation: else after a terminating branch (auto-fixable).
function elseAfterReturn(bool $flag): int
{
    if ($flag) {
        return 1;
    } else {
        return 2;
    }
}

// Violations: elseif and else in one chain are flagged separately (auto-fixable).
function chainedElseIf(int $value): string
{
    if ($value === 1) {
        return 'one';
    } elseif ($value === 2) {
        return 'two';
    } else {
        return 'many';
    }
}

// Violation: "else if" is flagged like elseif (auto-fixable).
function elseIfWithSpace(bool $flag): int
{
    if ($flag) {
        return 1;
    } else if (!$flag) {
        return 2;
    }

    return 3;
}

// Violation: first branch does not exit early — flagged, too complex to auto-fix safely.
function elseWithoutEarlyExit(bool $flag): int
{
    $result = 0;

    if ($flag) {
        $result = 1;
    } else {
        $result = 2;
    }

    return $result;
}

// Violations: nested conditionals each produce their own distinct violation.
function nestedElse(bool $outer, bool $inner): int
{
    if ($outer) {
        if ($inner) {
            return 1;
        } else {
            return 2;
        }
    } else {
        return 3;
    }
}

// Violation: alternative syntax else — flagged, not auto-fixable.
function alternativeSyntax(bool $flag): int
{
    if ($flag) :
        return 1;
    else :
        return 2;
    endif;
}

// Violation: comment between the closing brace and else — flagged, not
// auto-fixed (the rewrite would silently delete the comment).
function commentBeforeElse(bool $flag): int
{
    if ($flag) {
        return 1;
    } // @phpstan-ignore-line
    else {
        return 2;
    }
}

// Violation: comment between the closing brace and elseif — flagged, not
// auto-fixed for the same reason.
function commentBeforeElseIf(bool $flag): int
{
    if ($flag) {
        return 1;
    } /* explanation */ elseif (!$flag) {
        return 2;
    }

    return 3;
}

// Violation: comment between "else" and "if" — flagged, not auto-fixed
// (the rewrite deletes the tokens between the two keywords).
function commentInsideElseIf(bool $flag): int
{
    if ($flag) {
        return 1;
    } else /* why */ if (!$flag) {
        return 2;
    }

    return 3;
}

// Violation: compact single-line if/else — flagged, not auto-fixed
// (non-canonical layout would splice the following line onto the statement).
function compactSingleLine(bool $flag): int
{
    if ($flag) { return 1; } else { return 2; }
}

// Violation: compact single-line if/elseif — flagged, not auto-fixed
// (non-canonical layout would mis-indent the rewritten if).
function compactSingleLineElseIf(int $value): int
{
    if ($value === 1) { return 1; } elseif ($value === 2) { return 2; }

    return 3;
}

// Violation: inline else body after a canonical if — flagged, not auto-fixed
// (removing the inline closing brace would splice the following line).
function inlineElseBody(bool $flag): int
{
    if ($flag) {
        return 1;
    } else { return 2; }
}

// Violation: braceless else — flagged, not auto-fixed (no curly scope).
function bracelessElse(bool $flag): int
{
    if ($flag)
        return 1;
    else
        return 2;
}

// Violation: braceless else if — flagged, not auto-fixed (no curly scope).
function bracelessElseIf(bool $flag): int
{
    if ($flag)
        return 1;
    else if (!$flag)
        return 2;

    return 3;
}

<?php

declare(strict_types=1);

/**
 * Threshold fixture for CleanCode.Conditionals.TypeDiscriminatorDispatch.
 *
 * One two-branch `switch` and one two-branch `if` chain, and nothing else, so
 * $minimumBranches is the only thing that can change the outcome: both are
 * silent at the shipped default of 3 and both report once it is lowered to 2.
 */

function twoCaseSwitch(object $shape): string
{
    switch ($shape->type) {
        case 'circle':
            return 'Circle';
        case 'square':
            return 'Square';
    }

    return 'Unknown';
}

function twoBranchIf(object $shape): string
{
    if ($shape->type === 'circle') {
        return 'Circle';
    } else {
        return 'Unknown';
    }
}

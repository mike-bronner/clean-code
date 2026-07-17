<?php

// Single-level ternary assignment.
$status = $isActive ? 'active' : 'inactive';

// Short ternary assignment.
$label = $name ?: 'anonymous';

// Single-level ternary in a return.
function describeCount(int $count): string
{
    return $count > 0 ? 'some' : 'none';
}

// Multi-line single-level ternary.
$message = $isEnabled
    ? 'feature enabled'
    : 'feature disabled';

// An if/elseif/else chain is beyond RequireTernaryOperator's scope.
if ($score > 90) {
    $grade = 'A';
} elseif ($score > 80) {
    $grade = 'B';
} else {
    $grade = 'C';
}

// An if without an else cannot become a ternary on its own.
if ($isAdmin) {
    $permissions = 'all';
}

// Branches with multiple statements cannot collapse into a ternary.
if ($isVerbose) {
    $prefix = 'debug';
    $suffix = 'end';
} else {
    $prefix = 'info';
}

// Branches assigning different variables cannot merge into one ternary.
if ($swap) {
    $left = 1;
} else {
    $right = 2;
}

// Compound assignments are not detected by RequireTernaryOperator — a
// documented limitation, see docs/standards/conditionals-ternary-conditionals.md.
if ($hasPrefix) {
    $path .= '/prefix';
} else {
    $path .= '/plain';
}

// Braceless if/else is not supported by RequireTernaryOperator — a
// documented limitation, see docs/standards/conditionals-ternary-conditionals.md.
if ($useCache)
    $driver = 'redis';
else
    $driver = 'file';

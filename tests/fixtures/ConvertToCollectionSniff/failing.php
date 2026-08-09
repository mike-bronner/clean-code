<?php

// Negative: bare calls to the shipped default trio at global scope.
$names = array_map(static fn (array $row): string => $row['name'], $rows);
$active = array_filter($rows, static fn (array $row): bool => $row['active']);
$total = array_reduce($rows, static fn (int $carry, int $row): int => $carry + $row, 0);

// Negative: PHP resolves function names case-insensitively, so a shouted call
// reaches the same native function.
$upper = ARRAY_MAP('strtoupper', $names);

// Negative: a leading backslash is the global function spelled explicitly, not
// a namespaced symbol of the same name.
$escaped = \array_map('rawurlencode', $names);

// Negative: inside a function body, not only at file scope.
function summarize(array $rows): array
{
    return array_filter($rows);
}

// Negative: inside a method body too.
class Report
{
    public function total(array $rows): int
    {
        return array_reduce($rows, static fn (int $carry, int $row): int => $carry + $row, 0);
    }
}

// Negative: PHP 8.1 first-class callable syntax still names the native
// function and still puts an open parenthesis after it, so it is flagged.
$mapper = array_map(...);

// Negative: nested calls each report at their own token, so one line carries
// two warnings.
$trimmed = array_map('trim', array_filter($rows));

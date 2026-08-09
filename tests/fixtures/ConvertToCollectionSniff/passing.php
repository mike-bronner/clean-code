<?php

// Positive: the Collection pipelines the standard asks for.
$names = collect($rows)->map(static fn (array $row): string => $row['name']);
$active = collect($rows)->filter(static fn (array $row): bool => $row['active']);
$total = collect($rows)->reduce(static fn (int $carry, int $row): int => $carry + $row, 0);

// Positive: native array functions outside the configured list stay silent.
// array_values and array_walk are the two the configurability tests switch on,
// so their silence here is what proves the default list drives detection.
$keys = array_keys($rows);
$values = array_values($rows);
$combined = array_combine($keys, $values);
array_walk($rows, static fn (int $row): int => $row);
usort($rows, static fn (int $a, int $b): int => $a <=> $b);

// Positive: a configured name that is not followed by an open parenthesis is a
// symbol reference rather than a call. A class of that name used as a type
// hint, and a bare constant fetch, are what exercise that test.
function describe(array_map $mapper): string
{
    return $mapper->label;
}

$fallback = array_reduce;

// Positive: a named argument spelled like a configured function. PHPCS gives
// it T_PARAM_NAME rather than T_STRING, so register() never offers it to the
// sniff at all — recorded here so nobody adds a guard for a shape that cannot
// reach the code.
configure(array_map: true);

// Positive: a configured name reached through an object operator is a method
// call on some other class, not the native function.
$transformer->array_map($rows);
$transformer?->array_filter($rows);

// Positive: the same names behind a double colon are static calls.
Transformer::array_map($rows);
Transformer::array_reduce($rows);

// Positive: declaring a function or a method of that name is not calling it.
function array_map(array $rows): array
{
    return $rows;
}

class Transformer
{
    public function array_filter(array $rows): array
    {
        return $rows;
    }

    public static function array_reduce(array $rows): array
    {
        return $rows;
    }
}

// Positive: the name as a string or a property is not a call.
$callback = 'array_map';
$property = $object->array_filter;

// Positive: instantiating a class of that name is not calling the function.
new array_reduce($rows);

// Positive: a namespaced function of the same name is a different symbol.
// This file declares no namespace, so namespace\array_filter() would be the
// global function here rather than a different symbol — it lives in
// failing.php, and namespaced.php carries the namespace-relative negative.
App\Support\array_map($rows);
App\Support\array_reduce($rows);

// Positive: new keeps instantiating whichever spelling of the class name
// follows it. Both spellings name the global class here, and both are flagged
// as calls once the keyword is gone — \array_map() in failing.php,
// namespace\array_map() in the global block of namespaced-blocks.php — so the
// silence below is new doing the work rather than the spelling.
new \array_reduce($rows);
new namespace\array_map($rows);

// Positive: a declaration that returns by reference is still a declaration.
// The & stands between the keyword and the name, at file scope and in a class
// alike.
function &array_filter(array $rows): array
{
    return $rows;
}

class ReferenceTransformer
{
    public function &array_map(array $rows): array
    {
        return $rows;
    }

    public static function &array_reduce(array $rows): array
    {
        return $rows;
    }
}

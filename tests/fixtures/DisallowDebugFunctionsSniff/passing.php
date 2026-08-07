<?php

// Positive: ordinary code calling nothing on the debug list.
$total = array_sum($amounts);
$label = strtoupper($name);
$rows = array_filter($records, static fn (array $row): bool => $row['active']);

report($total);
logger()->info('processed', ['total' => $total]);

// Positive: a debug name reached through an object operator is a method call
// on some other class, not the global debug function the standard forbids.
$debugger->dump($value);
$debugger?->dump($value);
$profiler->ray($value);

// Positive: the same name behind a double colon is a static call.
Debugger::dump($value);
Profiler::var_dump($value);

// Positive: declaring a method or function of that name is not calling it.
function dump(mixed $value): void
{
}

class Renderer
{
    public function dd(string $view): string
    {
        return $view;
    }
}

// Positive: the name as a string, a property, or a class is not a call.
$callback = 'var_dump';
$property = $object->var_dump;

new Dump($value);

// Positive: a namespaced function of the same name is a different symbol.
App\Utils\dump($value);
namespace\dump($value);

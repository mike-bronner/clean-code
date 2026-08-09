<?php

use function Acme\Support\debug_zval_dump;

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
App\Support\print_r($value);
namespace\debug_zval_dump($value);
App\Support\debug_print_backtrace($value);

// Positive: the PHPMD DevelopmentCodeFragment names behind an object, nullsafe,
// or static operator are calls on some other class, not the global functions.
$debugger->print_r($value);
$debugger?->debug_print_backtrace($value);
Debugger::debug_zval_dump($value);

// Positive: the `use function` import above binds the bare name to another
// namespace's function, so this call never reaches PHP's own — verified by
// executing the shape, not inferred from the token stream.
debug_zval_dump($value);

// Positive: a return-by-reference declaration is still a declaration. The `&`
// sits between the keyword and the name, which is what used to hide it.
function &ray(mixed $value): array
{
    return [$value];
}

// Positive: an attribute names a class, never a function.
#[dd(1)]
class Marker
{
}

// Positive: instantiation behind a leading qualifier. The separator hides the
// `new` from a check that only reads the token directly before the name.
new \print_r();
new namespace\var_dump();

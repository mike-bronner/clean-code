<?php

// Every `probe*` name in this fixture is one the helper must NOT call a global
// function call. Each shape appears once, and its name says which shape it is.

namespace App;

use function Acme\Support\probeImported;
use function Acme\Support\probeSource as probeAliased;
use function Acme\Group\{probeGrouped, probeRenamed as probeGroupAlias};
use Acme\Mixed\{function probeMixed, const PROBE_CONSTANT};

// Negative: a bare call the import redirects to another namespace's function.
probeImported($value);
probeAliased($value);
probeGrouped($value);
probeGroupAlias($value);
probeMixed($value);

// Negative: member access — the name belongs to some object or class.
$service->probeMethod($value);
$service?->probeNullsafeMethod($value);
Service::probeStatic($value);

// Negative: declarations, including the return-by-reference form whose `&`
// hides the `function` keyword from a check that reads one token back.
function probeDeclared(mixed $value): void
{
}

function &probeByReference(mixed $value): array
{
    return [$value];
}

class Probes
{
    /** @var array<int, string> */
    private array $rows = [];

    public function probeMethodDeclaration(string $view): string
    {
        return $view;
    }

    /** @return array<int, string> */
    public function &probeByReferenceMethod(): array
    {
        return $this->rows;
    }
}

// Negative: instantiation, bare and behind either leading qualifier.
new probeInstance();
new \probeGlobalInstance();
new namespace\probeRelativeInstance();
new Acme\probeNamespacedInstance();

// Negative: a qualified name resolves outside the global namespace.
Acme\Support\probeQualified($value);
namespace\probeRelative($value);

// Negative: an attribute name is a class, not a function.
#[probeAttribute(1)]
class Annotated
{
}

// Negative: the name as a string or a property is not a call at all.
$callback = 'probeString';
$property = $service->probeNotCalled;

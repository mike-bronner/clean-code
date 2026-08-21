<?php

// Every shape here sits in the global namespace, which is where two verdicts
// invert: `namespace\` resolves against the namespace in force, and an import
// of a global function under its own name redirects nothing.

use function probeSelfImport;
use function probeSelfSame as probeSelfSame;
use function probeSourceRenamed as probeSelfAlias;

// Positive: the namespace in force is the global one, so the relative
// qualifier reaches PHP's own function exactly as a leading separator does.
namespace\probeRelativeGlobal($value);

// Negative: qualifying a name never changes what the construct is. The
// instantiation keeps its exclusion here, where the qualifier itself resolves
// globally and so cannot carry the exclusion on its own.
new namespace\probeRelativeInstance();

// Positive: importing a global function under its own name binds the very
// symbol a bare call already reaches, so the call is still that function.
probeSelfImport($value);
probeSelfSame($value);

// Negative: an unqualified source under a *different* alias does bind another
// symbol, so this call reaches `probeSourceRenamed` rather than PHP's own
// function of the name written here.
probeSelfAlias($value);

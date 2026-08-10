<?php

/**
 * The shapes where this sniff and PHPMD 2.15.0 disagree, kept out of
 * passing.php and failing.php so both of those can claim exact parity.
 *
 * The disagreement runs in both directions, and
 * tests/Standards/SuperglobalsTest.php asserts each direction separately.
 */

// ---------------------------------------------------------------------------
// Stricter than PHPMD: file scope. PHPMD's rule is MethodAware and
// FunctionAware, so it inspects function and method bodies only and reports
// nothing here. A superglobal read at file scope is the same defect wherever
// it sits, so this sniff reports it.
// ---------------------------------------------------------------------------

$fileScopeQuery = $_GET['a'];
$fileScopeLegacy = $HTTP_POST_VARS['b'];

if (isset($_SERVER['HTTP_HOST'])) {
    $fileScopeGuarded = $_SERVER['HTTP_HOST'];
}

// ---------------------------------------------------------------------------
// Stricter than PHPMD: the ${name} interpolation form. PDepend does not model
// it, so PHPMD is silent; PHP reads the superglobal all the same. Deprecated
// in PHP 8.2 and removed in PHP 9, which makes the report more useful, not
// less — the code has to be rewritten anyway.
// ---------------------------------------------------------------------------

function dollarBraceInterpolation(): string
{
    return "id is ${_POST} here";
}

// ---------------------------------------------------------------------------
// Narrower than PHPMD: a static property access. `::` binds the name to a
// member of the named class, so no superglobal is reached — PHPMD's parser
// matches the variable's image without looking left and reports both of these.
// Replicating that would be replicating a false positive, which the rule's own
// acceptance criteria forbid.
// ---------------------------------------------------------------------------

class StaticHolder
{
    public static $_POST = [];

    public function readsOwnStaticProperty(): array
    {
        return [self::$_POST, static::$_POST, StaticHolder::$_POST];
    }
}

// ---------------------------------------------------------------------------
// One further divergence lives in parameters.php rather than here, because it
// cannot be separated from the promoted-parameter shape it has to be told apart
// from: a plain parameter nothing reads is reported by this sniff and not by
// PHPMD, whose rule keys on reads. This note sits at the foot of the file
// because every line above it is pinned by position in SuperglobalsTest.php.
// ---------------------------------------------------------------------------

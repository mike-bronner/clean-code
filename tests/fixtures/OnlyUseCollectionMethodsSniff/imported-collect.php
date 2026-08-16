<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use function MikeBronner\CleanCode\Support\makeArray as collect;

/**
 * `collect()` is the one Collection origin the sniff recognises by bare name,
 * so it is also the one a `use function … as collect;` import can take away.
 * The import binds the name for the whole file: `collect($rows)` here returns
 * whatever makeArray() returns, which is not provably a Collection — so the
 * call around it is neither reported nor rewritten. Rewriting it would spell
 * `collect($rows)->count()` against a plain array, which is a runtime fatal.
 *
 * This needs a fixture of its own for the same reason imported-function.php
 * does: the shadow is file-wide, and dropped into passing.php it would silence
 * every other `collect()` there and make those assertions vacuous.
 */
function anImportedCollectIsNotTheHelper(array $rows): int
{
    return count(collect($rows));
}

/**
 * The import binds the *unqualified* name only. A leading separator pins the
 * call to the global helper, so it is a Collection origin again and the call
 * around it stays reported *and* fixable.
 */
function aQualifiedCollectIsStillTheHelper(array $rows): int
{
    return count(\collect($rows));
}

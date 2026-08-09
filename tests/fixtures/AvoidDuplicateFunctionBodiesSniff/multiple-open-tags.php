<?php

/**
 * Open-tag fixture for CleanCode.Functions.AvoidDuplicateFunctionBodies.
 *
 * The sniff registers on T_OPEN_TAG and scans the whole file from the first
 * one, so every later open tag has to be ignored. This file carries three,
 * separated by inline HTML, and one duplicated pair — which must be reported
 * once. Without the guard the scan runs once per open tag and reports the same
 * duplication three times over.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\AvoidDuplicateFunctionBodiesSniff\OpenTags;

function firstCopy(array $values): array
{
    $values = array_map('trim', $values);
    $values = array_filter($values);

    return array_values($values);
}

?>
<p>Inline markup between two PHP blocks.</p>
<?php

function secondCopy(array $values): array
{
    $values = array_map('trim', $values);
    $values = array_filter($values);

    return array_values($values);
}

?>
<p>And a third block follows.</p>
<?php

function unrelated(int $count): int
{
    $count = abs($count);
    $count = min($count, 10);

    return $count;
}

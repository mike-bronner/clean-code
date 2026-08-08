<?php

/**
 * The parity set for PHPMD's UnusedCode/UnusedFormalParameter rule: every
 * shape here is reported by both PHPMD and rules.xml, on the same line.
 *
 * Shapes where the two tools disagree live in divergences.php instead, and the
 * sniff codes rules.xml excludes live in excluded-codes.php, so this fixture
 * can carry an unqualified parity claim.
 */

declare(strict_types=1);

function onlyParameterUnused(string $unused): string
{
    return 'constant';
}

function unusedBeforeLastUsed(string $unusedFirst, string $used): string
{
    return $used;
}

function unusedAfterLastUsed(string $used, string $unusedSecond): string
{
    return $used;
}

class StandaloneReporter
{
    public function record(string $unusedReason): void
    {
        echo 'recorded';
    }

    /**
     * The docblock below names $ghost in prose only. A comment is not a use.
     *
     * @param string $ghost
     */
    public function mentionInDocblockOnly(string $ghost): void
    {
        echo 'nothing';
    }

    public function __invoke(string $unusedInvoked): void
    {
        echo 'invoked';
    }
}

class StandaloneLedger
{
    // A plain constructor parameter, deliberately left unpromoted: promotion
    // would make the sniff skip it as class state. Unpromoted, it is a
    // parameter like any other, and nothing reads it.
    public function __construct(string $unusedReference)
    {
        echo 'opened';
    }
}

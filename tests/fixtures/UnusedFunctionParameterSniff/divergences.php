<?php

/**
 * Every shape where this ruleset and PHPMD 2.15.0 disagree about
 * UnusedCode/UnusedFormalParameter. Both directions are here, and both are set
 * out in docs/phpmd/unusedcode-unusedformalparameter.md.
 *
 * Stricter here than in PHPMD — rules.xml reports, PHPMD says nothing:
 *   - a closure parameter
 *   - an arrow-function parameter
 *   - a parameter reachable only through func_get_args()
 *
 * Looser here than in PHPMD — PHPMD reports, rules.xml says nothing:
 *   - an empty or comment-only body
 *   - a method that is NOT an override, in a class that extends another
 *   - a method that is NOT an override, in a class that implements an interface
 *   - __unserialize(), which the sniff exempts as magic and PHPMD does not
 */

declare(strict_types=1);

interface Signer
{
    public function sign(string $document): string;
}

class Ledger
{
    public function post(string $entry): string
    {
        return $entry;
    }
}

// Stricter: PHPMD's rule visits functions and methods only, never closures.
$closure = static function (string $unusedInClosure): string {
    return 'constant';
};

// Stricter: arrow functions are closures too, and equally invisible to PHPMD.
$arrow = static fn (string $unusedInArrow): string => 'constant';

/**
 * Stricter: func_get_args() reaches every parameter, so PHPMD treats them all
 * as used. The sniff walks tokens and sees no $collected, so it reports one.
 * This is the single shape where this ruleset over-reports.
 *
 * @return array<int, mixed>
 */
function readsArgumentsIndirectly(string $collected): array
{
    return func_get_args();
}

class DerivedLedger extends Ledger
{
    // Looser: not an override of anything on Ledger, so PHPMD reports it.
    // rules.xml excludes FoundInExtendedClass, which is what buys the
    // override exemption below, and this report goes with it.
    public function archive(string $unusedInDerived): void
    {
        echo 'archived';
    }

    // Both silent: a genuine override, whose signature Ledger fixes.
    public function post(string $entry): string
    {
        return 'overridden';
    }
}

class NotarySigner implements Signer
{
    // Looser: not an override of anything on Signer, so PHPMD reports it.
    public function notarize(string $unusedInImplementor): void
    {
        echo 'notarized';
    }

    // Both silent: implements the interface signature.
    public function sign(string $document): string
    {
        echo 'signed';

        return 'signature';
    }
}

class Snapshot
{
    // Looser: the sniff exempts an empty body outright — a class stubbing out
    // several interface methods should not be told off for each. PHPMD reports.
    public function reset(string $unusedInEmptyBody): void
    {
    }

    /**
     * Looser: the sniff's magic-method list is wider than PHPMD's, which only
     * exempts __call, __callStatic, __get, __set, __isset, __unset, and
     * __set_state. PHPMD reports $data here.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        echo 'restored';
    }
}

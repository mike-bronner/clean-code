<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter;

/**
 * The parity set: every shape PHPMD's UnusedFormalParameter reports and this
 * sniff reports too.
 *
 * Verified by running PHPMD 2.15.0 over this file with a ruleset enabling only
 * rulesets/unusedcode.xml/UnusedFormalParameter, and comparing its report line
 * by line with this sniff's. Every signature here is deliberately kept on one
 * line so that the two line numbers are directly comparable — PHPMD reports at
 * the declaration, this sniff at the parameter, and only a multi-line signature
 * separates them (that shape is pinned in divergences.php).
 *
 * tests/Standards/UnusedFormalParameterTest.php holds the line and column
 * assertions; the invocation and its output are quoted there.
 */

// A plain function whose body never reads the parameter.
function plainUnused(string $unusedA): void
{
    echo 'x';
}

// An empty body reads nothing. Generic.CodeAnalysis.UnusedFunctionParameter
// exempts this shape; PHPMD reports it, and so does this sniff.
function emptyBody(string $unusedB): void
{
}

// A comment-only body reads nothing either. Same exemption, same correction.
function commentOnlyBody(string $unusedC): void
{
    // Deliberately nothing.
}

// func_num_args() does not reach the parameters the way func_get_args() does,
// and PHPMD reports through it. Measured, not assumed.
function viaFuncNumArgs(string $unusedD): void
{
    var_dump(func_num_args());
}

// compact() naming a different variable is not a read of this one.
function compactNamingAnother(string $unusedE): array
{
    $other = 1;

    return compact('other');
}

// A parameter mentioned only in the docblock is still unread.
/**
 * Handles $unusedF somehow.
 */
function docblockMentionOnly(string $unusedF): void
{
    echo 'x';
}

// A variadic that collects nothing the body reads.
function variadicUnused(string ...$unusedG): void
{
    echo 'x';
}

// A by-reference parameter the body never touches, so it writes nothing back.
function byReferenceUnused(string &$unusedH): void
{
    echo 'x';
}

// A default value does not make a parameter used.
function defaultedUnused(string $unusedI = 'fallback'): void
{
    echo 'x';
}

// Two parameters, one read and one not: only the dead one is reported.
function partiallyUsed(string $usedJ, string $unusedK): void
{
    echo $usedJ;
}

abstract class ReportingBase
{
    // The class is abstract, but this method is its own and has a body.
    public function ownMethod(string $unusedL): void
    {
        echo 'x';
    }
}

interface ReportingContract
{
    public function handle(string $payload): void;
}

class ReportingChild extends ReportingBase implements ReportingContract
{
    public function handle(string $payload): void
    {
        echo $payload;
    }

    // Not an override: the name appears in neither same-file ancestor. This is
    // the shape the excluded-code approach could not reach, because it decided
    // the exemption per class rather than per method.
    public function ownOnly(string $unusedM): void
    {
        echo 'x';
    }
}

class MagicButNotFixed
{
    // __unserialize takes a parameter and its signature is the author's own as
    // far as both tools are concerned.
    public function __unserialize(array $unusedN): void
    {
        echo 'x';
    }

    // __invoke is not a fixed-signature magic method in either tool.
    public function __invoke(string $unusedO): void
    {
        echo 'x';
    }
}

class StaticHolder
{
    public static function staticUnused(string $unusedP): void
    {
        echo 'x';
    }
}

trait ReportingTrait
{
    public function inTrait(string $unusedQ): void
    {
        echo 'x';
    }
}

enum ReportingEnum
{
    case One;

    public function inEnum(string $unusedR): void
    {
        echo 'x';
    }
}

// The word-boundary discriminator. The body reads $idleTimer, whose name
// contains this parameter's whole name as a prefix. Drop the word boundary
// from the sniff's read test and $id looks read, so this report disappears —
// PHPMD reports it, so that would be coverage lost to a one-character bug.
function prefixIsNotARead(string $id): void
{
    $idleTimer = 1;

    echo $idleTimer;
}

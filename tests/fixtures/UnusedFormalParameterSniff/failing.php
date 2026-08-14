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

// A constructor parameter that is not promoted is the author's own, exactly
// like any other. Only promotion turns one into class state.
class PlainConstructor
{
    public function __construct(string $unusedS)
    {
        echo 'x';
    }
}

// A comment naming the parameter is not a read of it. The body is read as
// code, not as raw text, so a name that appears only in a comment leaves the
// parameter as dead as it was.
function commentMentionOnly(string $unusedT): void
{
    // $unusedT is named here and nowhere else.
    echo 'x';
}

// A single-quoted string does not interpolate, so the name inside it is
// printed rather than read.
function stringLiteralMentionOnly(string $unusedU): void
{
    echo 'this prints $unusedU literally';
}

// Inline HTML is output too, for the same reason.
function inlineHtmlMentionOnly(string $unusedV): void
{
    ?>
    <p>$unusedV</p>
    <?php
}

class LineCommentAnnotation
{
    // A line comment is not a docblock, and only a docblock carries the
    // annotation. PHPMD reads the method's doc comment, which is the same
    // distinction.
    // @inheritdoc
    public function handle(string $unusedW): void
    {
        echo 'x';
    }
}

class AttributeArgument
{
    // Override named inside another attribute's argument list is a class
    // reference, not the #[\Override] attribute.
    #[Listens(handler: Override::class)]
    public function handle(string $unusedX): void
    {
        echo 'x';
    }
}

trait CollidingTrait
{
    public function collide(string $value): string
    {
        return $value;
    }
}

// A trait the class uses itself does not make the class's own method an
// override: PHP gives the class's own declaration precedence over the trait's,
// and PHPMD asks only about the parent chain.
class UsesCollidingTrait
{
    use CollidingTrait;

    public function collide(string $unusedY): string
    {
        return 'x';
    }
}

// A heredoc interpolates, so a name inside one is a read — but a call written
// inside one is still printed rather than run. PHPMD matches a call node, so it
// reports through this too.
function heredocMentionsFuncGetArgs(string $unusedZ): void
{
    echo <<<TEXT
    Example: func_get_args() would return every argument.
    TEXT;
}

// The same heredoc, naming the parameter through a compact() that is text.
function heredocMentionsCompact(string $unusedAA): void
{
    echo <<<TEXT
    Example: compact('unusedAA') would build an array.
    TEXT;
}

// An interpolated double-quoted string is text for the same reason. The local
// variable is what makes PHP tokenize this as one: a string with nothing to
// interpolate is a plain quoted string instead.
function interpolationMentionsFuncGetArgs(string $unusedAB): void
{
    $note = 'documentation';

    echo "Example ($note): func_get_args() would return every argument.";
}

// The same interpolated string, naming the parameter through a compact().
function interpolationMentionsCompact(string $unusedAC): void
{
    $note = 'documentation';

    echo "Example ($note): compact('unusedAC') would build an array.";
}

// A shell string is text too. Its variables are tokenized apart from that text,
// which is why a read inside one still counts and this call does not.
function shellStringMentionsFuncGetArgs(string $unusedAD): void
{
    echo `echo 'func_get_args() returns every argument'`;
}

// A nowdoc does not interpolate, so the name inside it is printed literally,
// exactly like the single-quoted string above.
function nowdocMentionOnly(string $unusedAE): void
{
    echo <<<'RAW'
    $unusedAE is printed as written.
    RAW;
}

class AttributeStringArgument
{
    // An attribute's string argument is text, whatever it spells out. This one
    // spells out the tail of an attribute list; it is not the #[\Override]
    // attribute, and PHP would not accept it as one.
    #[Listens(handler: 'first, Override(second')]
    public function handle(string $unusedAF): void
    {
        echo 'x';
    }
}

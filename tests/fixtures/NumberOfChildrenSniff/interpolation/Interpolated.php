<?php

declare(strict_types=1);

namespace Fixture\Interpolation;

/**
 * String interpolation opens a brace the tokenizer does not hand over as a bare
 * `{`, and closes it with a bare `}`. A parse that counts only the bare braces
 * therefore drops a level on every interpolation, closes the enclosing class
 * body early, and reads any trait `use` written after that point in the same
 * body as a namespace import.
 *
 * Hence the deliberate ordering below: the trait `use` sits *after* the method
 * that interpolates, which is where a class body is still open but a
 * bare-braces-only count already believes it closed. A `use` written above the
 * method — the usual place — is reached before the drift and proves nothing.
 *
 * Each class is that shape once, for one of the three spellings: the `{$expr}`
 * brace form, the `${expr}` dollar form, and an interpolation inside a heredoc
 * body. Each pairs with a trait whose short name is also a class in the global
 * namespace (Bare.php), and each is followed by a class extending that short
 * name. Resolved correctly the name is this file's namespace and reaches a
 * trait, which no rule counts children for; resolved through a leaked import it
 * is the global class, and Bare.php is accused of a child it does not have.
 */
trait Braced
{
}

trait Dollared
{
}

trait Heredoc
{
}

class UsesBraced
{
    public function render(string $value): string
    {
        return "value: {$value}";
    }

    use Braced;
}

class ChildOfBraced extends Braced
{
}

class UsesDollared
{
    public function render(string $value): string
    {
        return "value: ${value}";
    }

    use Dollared;
}

class ChildOfDollared extends Dollared
{
}

class UsesHeredoc
{
    public function render(string $value): string
    {
        return <<<TEXT
            value: {$value}
            TEXT;
    }

    use Heredoc;
}

class ChildOfHeredoc extends Heredoc
{
}

/**
 * The live control for this fixture set: an ordinary parent and child, with no
 * interpolation and no trait anywhere near them. It is reported at the lowered
 * threshold the interpolation test runs at, which is what makes that test's
 * silence on Bare.php an assertion about resolution rather than about the sniff
 * or the run being inert.
 */
class Anchor
{
}

class AnchorChild extends Anchor
{
}

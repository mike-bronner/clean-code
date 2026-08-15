<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\BooleanGetMethodName;

class Article
{
    /**
     * A `bool` property whose name starts with `get`. Not a method, so neither
     * tool looks at it.
     */
    public bool $getFeatured = true;

    /**
     * The compliant spellings the rule asks for.
     *
     * @return bool
     */
    public function isPublished()
    {
        return true;
    }

    /**
     * @return boolean
     */
    public function hasAuthor()
    {
        return true;
    }

    /**
     * A `get` prefix on a method that returns something other than a boolean.
     *
     * @return string
     */
    public function getTitle()
    {
        return 'title';
    }

    /**
     * A native non-boolean return type, no docblock type at all.
     */
    public function getWordCount(): int
    {
        return 0;
    }

    /**
     * A union wider than `bool` carries a value, not a yes/no answer, so it is
     * not a boolean getter — widening the type check to "mentions bool" would
     * report this.
     *
     * @return bool|string
     */
    public function getStatus()
    {
        return 'draft';
    }

    /**
     * `bool` as a *parameter* type and as the type of a local, never as the
     * return type.
     */
    public function getRenderer(bool $inline)
    {
        $flag = true;

        return $inline === $flag ? 'inline' : 'block';
    }

    /**
     * A misspelled tag is not an `@return` tag, so no return type is declared
     * and neither tool reports.
     *
     * @returns bool
     */
    public function getSyndicated()
    {
        return true;
    }

    /**
     * Neither a docblock type nor a native return type — nothing declares this
     * method boolean, so it is not a boolean getter to either tool.
     */
    public function getSlugified()
    {
        return true;
    }

    /**
     * The prefix has to be at the start. `forget`, `target`, and `budget` all
     * contain `get` and none of them is a getter.
     *
     * @return bool
     */
    public function forgetCache()
    {
        return true;
    }

    /**
     * This doc comment belongs to the constant below it, not to the method
     * below that — a declaration only owns the comment that sits immediately
     * above it.
     *
     * @return bool
     */
    public const SYNDICATED = true;

    public function getIndexable()
    {
        return true;
    }

    /**
     * An `@return` tag with no type at all must not borrow the next tag's
     * text.
     *
     * @return
     * @see bool
     */
    public function getPromotable()
    {
        return true;
    }
}

/**
 * A plain function at file scope. PHPMD's rule is MethodAware only, so it never
 * visits one, and neither does this sniff.
 *
 * @return bool
 */
function getVerified()
{
    return true;
}

/**
 * A closure and an arrow function are not methods either, whatever they are
 * assigned to.
 */
$getEnabled = function (): bool {
    return true;
};

$getCached = fn (): bool => true;

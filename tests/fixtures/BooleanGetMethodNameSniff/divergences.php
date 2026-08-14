<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\BooleanGetMethodName;

class Draft
{
    /**
     * A native `bool` return type and no `@return` tag at all. PHPMD reads only
     * the doc comment, so it never sees this one.
     */
    public function getVisible(): bool
    {
        return true;
    }

    /**
     * A nullable native return type. Same blind spot, plus the `?`.
     */
    public function getPublished(): ?bool
    {
        return null;
    }

    /**
     * A native union that reduces to `bool`.
     */
    public function getArchived(): bool|null
    {
        return null;
    }

    /**
     * A nullable *docblock* type. PHPMD's pattern requires `bool` or `boolean`
     * immediately after `@return `, so the leading `?` hides it.
     *
     * @return ?bool
     */
    public function getLocked()
    {
        return null;
    }

    /**
     * A docblock union that reduces to `bool`. PHPMD's pattern requires
     * whitespace directly after `bool`, and here a `|` follows it.
     *
     * @return bool|null
     */
    public function getRestricted()
    {
        return null;
    }

    /**
     * A method of an *anonymous* class. PDepend does not surface one to a
     * MethodAware rule, so PHPMD never visits it; a token scan sees no
     * difference between it and any other method.
     */
    public function build(): object
    {
        return new class {
            /**
             * @return bool
             */
            public function getReady()
            {
                return true;
            }
        };
    }

    /**
     * A *named* function declared inside a method is still a function, not a
     * method, so both tools stay silent. Its `conditions` list holds the
     * enclosing class, so only reading the *innermost* scope tells the two
     * apart.
     */
    public function defineHelper(): void
    {
        /**
         * @return bool
         */
        function getNested()
        {
            return true;
        }
    }
}

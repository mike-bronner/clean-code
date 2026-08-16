<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ActionMethodReturn;

class Post
{
    /**
     * A property whose name starts with an action verb. Not a method.
     */
    public int $setCount = 0;

    /**
     * The compliant shape: an action verb, and nothing handed back.
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * `never` says the same thing as `void` about a value: none comes back.
     */
    public function sendReceipt(): never
    {
        throw new \RuntimeException('unreachable');
    }

    /**
     * No declared return type and only a bare `return;`. Flow control, not a
     * value — reading "the body has a return" without asking what follows it
     * would report this.
     */
    public function resetCache()
    {
        if ($this->title === '') {
            return;
        }

        $this->cache = [];
    }

    /**
     * No declared return type and no `return` at all.
     */
    public function clearTags()
    {
        $this->tags = [];
    }

    /**
     * `settle` is not `set`: the verb has to end where a camelCase word ends,
     * and a lower-case character after it means it never was a word.
     */
    public function settleInvoice(): bool
    {
        return true;
    }

    /**
     * `address` is not `add`, by the same boundary.
     */
    public function addressOf(): string
    {
        return '221B Baker Street';
    }

    /**
     * `SetSubtitle` starts with a capital, so it is not the configured `set`.
     * A method named this way is a *casing* violation, owned by Naming: Casing
     * conventions (#22/#100); folding case here would report one declaration
     * twice and describe it correctly neither time.
     */
    public function SetSubtitle(): string
    {
        return $this->title;
    }

    /**
     * Not an action verb at all, so what it returns is nobody's business here.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * The fluent interface, exempt while $allowFluentInterface is on: `static`,
     * `self`, a bare enclosing-class name, and a body that returns `$this`.
     */
    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function addTag(string $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    public function removeTag(string $tag): Post
    {
        unset($this->tags[$tag]);

        return $this;
    }

    /**
     * No declared type, and every value-return is `return $this;` — including
     * the early one, so the "every return" rule is exercised, not just "the
     * last return".
     */
    public function applyDefaults()
    {
        if ($this->title === '') {
            return $this;
        }

        $this->slug = $this->title;

        return $this;
    }

    /**
     * The method itself returns nothing. The closure inside it does, and the
     * closure is not this method — reading every `return` between the braces
     * would report this line.
     */
    public function storeDrafts()
    {
        $this->drafts = array_map(static function (string $draft): string {
            return trim($draft);
        }, $this->drafts);
    }

    /**
     * The same, one level further out: a named function declared in the body
     * still lists this method among its conditions.
     */
    public function updateCounters()
    {
        function setNextCounter(): int
        {
            return 1;
        }

        setNextCounter();
    }

    /**
     * An anonymous class declared in the body owns its own methods' returns,
     * and its own compliant `setMode()` is a method in its own right.
     */
    public function attachRenderer()
    {
        $this->renderer = new class () {
            public function setMode(): void
            {
                $this->mode = 'html';
            }

            public function render(): string
            {
                return 'html';
            }
        };
    }
}

abstract class Draft
{
    /**
     * No body, so no return to find, and `void` declares none anyway.
     */
    abstract public function saveDraft(): void;
}

interface Publishable
{
    /**
     * An interface method with no body and no declared return type: there is
     * nothing here that says a value comes back.
     */
    public function postArticle();
}

/**
 * A plain function, a closure, and an arrow function are not methods, so the
 * standard's "an action being taken on the class" does not describe any of
 * them.
 */
function saveEverything(): bool
{
    return true;
}

$deleteAll = function (): int {
    return 0;
};

$sendAll = fn (): int => 0;

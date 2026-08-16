<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ActionMethodReturn;

/**
 * The five spellings of a fluent interface, and nothing else — so the same file
 * reports nothing while $allowFluentInterface is on and reports all five once
 * it is off. Without both runs, a property that silenced the sniff outright
 * would look exactly like a working exemption.
 */
class Builder
{
    /**
     * The nullable spelling. The `?` only adds a third state to the same
     * object, so it is stripped before the type is read — leaving it in place
     * would report this line while the exemption is on.
     */
    public function resetName(): ?static
    {
        return $this->name === '' ? null : $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function addField(string $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    public function removeField(string $field): Builder
    {
        unset($this->fields[$field]);

        return $this;
    }

    public function resetFields()
    {
        $this->fields = [];

        return $this;
    }

    /**
     * The union spelling of the same nullable object. Dropping the `null`
     * member is what leaves `Builder` to compare against the class name;
     * keeping it would report this line while the exemption is on.
     */
    public function clearFields(): Builder|null
    {
        return $this->fields === [] ? null : $this;
    }
}

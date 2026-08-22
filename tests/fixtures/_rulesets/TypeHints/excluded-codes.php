<?php

// Both properties below trip a code rules.xml <exclude>s from the #45
// PropertyTypeHint rule. Both police docblock hygiene rather than a missing
// native hint, so through the master ruleset this file must be silent — and
// without the excludes both codes must fire.

class ExcludedPropertyCodes
{
    // MissingTraversableTypeHintSpecification: a traversable native hint with
    // no item type named in a docblock.
    private array $items = [];

    /**
     * UselessAnnotation: the docblock restates the native hint and adds nothing.
     *
     * @var string
     */
    private string $name = '';

    public function describe(): string
    {
        return $this->name . count($this->items);
    }
}

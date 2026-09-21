<?php

// Every declaration below trips one of the five codes CleanCode/ruleset.xml <exclude>s from
// the #70 ParameterTypeHint / ReturnTypeHint rules. Each acts on what a docblock
// says rather than on a missing native hint, so through the master ruleset this
// file must be silent — and without the excludes every code here must fire.

class ExcludedParameterCodes
{
    // MissingTraversableTypeHintSpecification: a traversable native hint with
    // no item type named in a docblock.
    public function eachItem(array $items): void
    {
        echo count($items);
    }

    /**
     * @param string $value
     */
    public function restatesTheNativeHint(string $value): void
    {
        echo $value;
    }
}

class ExcludedReturnCodes
{
    // MissingTraversableTypeHintSpecification, on the return side.
    public function listItems(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function restatesTheNativeReturn(): string
    {
        return 'value';
    }

    /**
     * LessSpecificNativeTypeHint: a native `void` the annotation says could be
     * narrowed to `never`. That is the only shape the code has — the sniff
     * raises it nowhere else — and it needs enableNeverTypeHint, which CleanCode/ruleset.xml
     * pins on.
     *
     * @return never
     */
    public function alwaysThrows(): void
    {
        throw new \RuntimeException('never returns');
    }
}

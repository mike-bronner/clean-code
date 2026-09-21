<?php

// #45's PropertyTypeHint shares the SlevomatCodingStandard.TypeHints.*
// namespace with the two sniffs #70 owns, and is live in the shipped
// CleanCode/ruleset.xml. This fixture pairs an unhinted classic property with an unhinted
// method so the test's sniff allowlist is exercised: the property error must
// stay out of #70's map while the parameter/return errors beside it stay in.
// A `TypeHints.` prefix match instead of the allowlist counts all three.
class PropertyBesideMethodHints
{
    private $leaked;

    public function unhinted($value)
    {
        return $value;
    }
}

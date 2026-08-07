<?php

// Every property is promoted in the constructor signature — visibility
// modifiers, readonly, and defaults are all expressed inline, so there is
// nothing left for the sniff to flag.
class PromotedEverything
{
    public function __construct(
        private string $name,
        protected readonly int $count = 0,
        public ?array $options = null,
    ) {
    }
}

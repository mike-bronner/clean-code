<?php

declare(strict_types=1);

// This file declares no namespace, so the global namespace *is* the current
// one. Both routes below therefore reach PHP's own functions and are flagged —
// the same spellings are exempt only inside a named namespace.

class Probe
{
    public function relative(): array
    {
        // `namespace\` resolves against the global namespace here.
        return namespace\func_get_args();
    }

    public function counted(): int
    {
        return namespace\func_num_args();
    }
}

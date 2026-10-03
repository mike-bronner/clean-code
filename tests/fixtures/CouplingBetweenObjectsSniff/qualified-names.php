<?php

declare(strict_types=1);

// Each qualified name is one token under PHP_CodeSniffer 4. The count stays the
// one the sniff has always reached: a relative `new` is not counted, a relative
// static reference or caught type and its rooted twin are one dependency, a
// qualified name resolves against the qualified namespace, and the class's own
// name drops out.

namespace App\Billing;

class Statement
{
    public function build(): void
    {
        new namespace\Invoice();
        namespace\Ledger::post();
        \Ledger::audit();
        Vendor\Client::make();
        \App\Billing\Vendor\Client::other();
        \App\Billing\Statement::fresh();

        try {
            $this->build();
        } catch (namespace\Failure $relative) {
        } catch (\Failure $rooted) {
        }
    }
}

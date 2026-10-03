<?php

declare(strict_types=1);

// A class constant read through a qualified class name is one name token under
// PHP_CodeSniffer 4. It is still a plain value, so both chains are candidates.

final class QualifiedLabels
{
    public function rooted(string $code): string
    {
        if ($code === 'a') {
            return \App\Status::Active;
        } elseif ($code === 'b') {
            return \App\Status::Blocked;
        } else {
            return \App\Status::Unknown;
        }
    }

    public function qualified(string $code): string
    {
        if ($code === 'a') {
            return App\Status::Active;
        } elseif ($code === 'b') {
            return App\Status::Blocked;
        } else {
            return App\Status::Unknown;
        }
    }
}

<?php

declare(strict_types=1);

class NotificationSender
{
    public function send(bool $silent): void
    {
        error_log((string) $silent);
    }
}

$broadcast = function ($force = true): void {
    error_log((string) $force);
};

$broadcast();

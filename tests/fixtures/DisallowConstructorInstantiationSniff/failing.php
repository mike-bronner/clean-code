<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Classes.DisallowConstructorInstantiation.
 *
 * Every `new` here sits directly in a `__construct` body, which is the whole
 * violation. The variant shapes — nested scopes, `throw new`, constructors in
 * other declaration forms — live in shapes.php, so this file stays the
 * contract sweep's simple floor.
 */

final class Mailer
{
}

final class Logger
{
}

final class Notifier
{
    private Mailer $mailer;

    private Logger $logger;

    public function __construct()
    {
        $this->mailer = new Mailer();
        $this->logger = new Logger();
    }
}

final class Report
{
    private Logger $logger;

    public function __construct(private Mailer $mailer)
    {
        $this->logger = new Logger();
    }

    public function build(): Logger
    {
        return $this->logger;
    }
}

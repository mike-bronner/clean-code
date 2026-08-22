<?php

// Two namespace blocks holding byte-identical declarations. Only the namespace
// differs: under App\Services the class name BillingService repeats its folder,
// under App\Billing nothing repeats anything. Slevomat's five Superfluous*Naming
// sniffs must report the same thing in both blocks, because none of them reads
// the namespace; this rule must report the first block only.

namespace App\Services {
    class BillingService
    {
    }

    interface PaymentInterface
    {
    }

    trait LoggingTrait
    {
    }

    abstract class AbstractGateway
    {
    }

    class TransportException extends \Exception
    {
    }

    class ParseError extends \Error
    {
    }
}

namespace App\Billing {
    class BillingService
    {
    }

    interface PaymentInterface
    {
    }

    trait LoggingTrait
    {
    }

    abstract class AbstractGateway
    {
    }

    class TransportException extends \Exception
    {
    }

    class ParseError extends \Error
    {
    }
}

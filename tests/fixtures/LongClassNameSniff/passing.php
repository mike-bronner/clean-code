<?php

declare(strict_types=1);

namespace Acme\WarehouseOperations\Replenishment\Scheduling;

use Acme\WarehouseOperations\Contracts\CustomerAddressBookSynchronizationHandler;

/**
 * Each of the four declaration keywords the sniff registers on, named at
 * exactly the default maximum of 40 bytes. PHPMD reports only above the
 * threshold, so all four are silent — the boundary's silent half.
 */
class OrderShipmentNotificationPreferenceStore
{
    /**
     * A property name far longer than the threshold. The rule measures type
     * names only.
     */
    public string $customerAddressBookSynchronizationSummaryPayload = '';

    /**
     * A method name far longer than the threshold, likewise not a type name.
     */
    public function renderTheCustomerAddressBookSynchronizationSummary(): object
    {
        $customerAddressBookSynchronizationSummaryPayloadBuffer = [];

        // An anonymous class has no declared name to measure. PHPCS tokenises
        // it as T_ANON_CLASS, which the sniff does not register.
        return new class ($customerAddressBookSynchronizationSummaryPayloadBuffer) {
            public function __construct(private array $rows)
            {
            }
        };
    }

    /**
     * A *reference* to a 41-byte type declared in another file. Only
     * declarations are measured, so neither the import above nor this
     * instantiation is flagged.
     */
    public function handler(): CustomerAddressBookSynchronizationHandler
    {
        return new CustomerAddressBookSynchronizationHandler();
    }
}

interface PaymentGatewayTransactionOutcomeMappable
{
    public function map(): string;
}

trait InvoiceLineItemDiscountCalculationsTrait
{
    public function discount(): int
    {
        return 0;
    }
}

enum SubscriptionBillingCycleTerminationState: string
{
    case Active = 'active';
    case Ended = 'ended';
}

/**
 * The fully-qualified name of this type is far longer than 40 bytes once the
 * file's namespace is counted, but PHPMD measures the declared short name, so
 * `Adjustments` is silent.
 */
final class Adjustments
{
}

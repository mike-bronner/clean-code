<?php

declare(strict_types=1);

namespace Acme\WarehouseOperations\Replenishment;

/**
 * The silent half of the boundary, and the sniff's near-miss inventory.
 *
 * Every field, parameter, and local declared here is exactly 20 bytes long once
 * the leading `$` is dropped — the default maximum, which PHPMD reports *above*
 * rather than *at*. Around them sit the shapes the sniff must not measure at
 * all: names that are not variables, and variables that belong to a member
 * access.
 */
class ReplenishmentScheduler
{
    /**
     * A class constant far longer than the threshold. The rule measures fields,
     * parameters, and locals; a constant is none of those.
     */
    public const CUSTOMER_ADDRESS_BOOK_SYNCHRONIZATION_PAYLOAD = 1;

    /**
     * A field at exactly the maximum.
     */
    protected string $replenishmentWindows = '';

    /**
     * A static field at exactly the maximum.
     */
    protected static int $scheduledOrderCounts = 0;

    /**
     * A method name far longer than the threshold. Not a variable either.
     */
    public function synchronizeCustomerAddressBookReplenishmentSchedules(): void
    {
    }

    /**
     * A parameter and a local, both at exactly the maximum. The local repeats
     * the field's name, which is fine — a field and a local sit in separate
     * containers, so neither hides the other.
     */
    public function schedule(string $warehouseLocationIds): string
    {
        $replenishmentWindows = $warehouseLocationIds;

        return $replenishmentWindows;
    }

    /**
     * Member accesses. In each of these the long name belongs to a member
     * rather than to a declaration, and PHPMD exempts every node under a
     * member-access prefix — the object on the left of `->`, `?->`, or `::`,
     * and the static field on the right of a `::`.
     */
    public function accessors(): void
    {
        echo $this->customerAddressBookSynchronizationPayload;
        echo self::$customerAddressBookSynchronizationCounter;
        echo static::$customerAddressBookSynchronizationRecord;

        $customerAddressBookSynchronizationHandler->dispatch();
        $customerAddressBookSynchronizationRegistry::create();
    }
}

/**
 * An interface method's parameter, at exactly the maximum. PHPMD's rule is not
 * `InterfaceAware`, but it is `MethodAware`, so an interface method's
 * parameters are measured in both tools.
 */
interface ReplenishmentSchedulable
{
    public function reschedule(int $warehouseLocationIds): void;
}

/**
 * An enum method's parameter and local, at exactly the maximum. Same reasoning
 * as the interface above: the rule reaches these through `MethodAware`.
 */
enum ReplenishmentState: string
{
    case Pending = 'pending';

    public function describe(int $warehouseLocationIds): string
    {
        $replenishmentWindows = (string) $warehouseLocationIds;

        return $replenishmentWindows;
    }
}

/**
 * Code at file scope is outside every context PHPMD's rule is aware of — it is
 * `ClassAware`, `MethodAware`, `FunctionAware`, and `TraitAware`, and none of
 * those covers file scope. A closure declared here is not a named function
 * either, so its parameter and local are out of scope too. All four of these
 * names are far longer than the threshold and all four are silent.
 */
$customerAddressBookSynchronizationBuffer = [];

$dispatch = function (array $customerAddressBookSynchronizationRows): int {
    $customerAddressBookSynchronizationTotal = count($customerAddressBookSynchronizationRows);

    return $customerAddressBookSynchronizationTotal;
};

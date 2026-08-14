<?php

declare(strict_types=1);

/**
 * The flagged half of the boundary. Every name below is 21 bytes once the
 * leading `$` is dropped — one over the default maximum of 20 — and each one
 * exercises a different way of introducing a variable, so a sniff that reached
 * only declarations, or only assignments, would leave some of them silent.
 *
 * Every name is distinct: a name is reported once per container, so reusing one
 * would hide the second occurrence rather than test it.
 */
class ReplenishmentScheduler
{
    protected string $replenishmentWindowId = '';

    protected static int $scheduledOrderCounter = 0;

    /**
     * A promoted constructor property is a parameter and a field at once.
     * PHPMD reports it at the parameter.
     */
    public function __construct(private array $warehouseLocationKeys = [])
    {
    }

    public function schedule(int $inventoryAdjustmentId): void
    {
        $shipmentManifestLines = $inventoryAdjustmentId;

        for ($purchaseOrderRevision = 0; $purchaseOrderRevision < 10; $purchaseOrderRevision++) {
            echo $shipmentManifestLines;
        }

        foreach ([1, 2] as $backorderedItemCounts) {
            echo $backorderedItemCounts;
        }
    }

    public function handle(): void
    {
        try {
            echo 1;
        } catch (\Throwable $carrierServiceLevelId) {
            echo $carrierServiceLevelId->getMessage();
        }

        [$distributionCenterIds] = [1];

        echo $distributionCenterIds;
    }

    /**
     * A closure's parameters and locals belong to the enclosing function's
     * container, because PHPMD finds them as children of that function.
     */
    public function dispatch(): callable
    {
        $pickedQuantitiesTotal = 0;

        return function (int $returnAuthorizationId) use ($pickedQuantitiesTotal): int {
            return $returnAuthorizationId + $pickedQuantitiesTotal;
        };
    }

    public function scopes(): void
    {
        global $customerReferenceCode;

        static $stockKeepingUnitCodes = 1;

        echo $customerReferenceCode . $stockKeepingUnitCodes;
    }
}

interface ReplenishmentSchedulable
{
    public function reschedule(int $deliveryAppointmentId): void;
}

enum ReplenishmentState: string
{
    case Pending = 'pending';

    public function describe(int $freightClassification): string
    {
        $palletConfigurationId = (string) $freightClassification;

        return $palletConfigurationId;
    }
}

function reconcileReplenishment(int $cycleCountVarianceIds): int
{
    $inboundReceiptLineIds = $cycleCountVarianceIds;

    return $inboundReceiptLineIds;
}

<?php

declare(strict_types=1);

/**
 * The prefix/suffix subtraction fixture. All six fields are over the default
 * maximum of 20 as written, so with the lists unconfigured every one of them is
 * reported — that unconfigured run is the control the configured runs are read
 * against, without which a sniff that had simply fallen silent on this file
 * would look like working subtraction.
 *
 * Configured with prefix `temporary` and suffixes
 * `Collection,Factory,MockCollection`, the first four fall to 20 or fewer and go
 * silent while the last two stay over. Which two survive is the point: see
 * tests/Standards/LongVariableTest.php for what each one pins.
 */
class WarehouseReplenishmentFixtures
{
    /**
     * 26 bytes; 17 once `temporary` comes off.
     */
    protected array $temporaryReplenishmentRows = [];

    /**
     * 27 bytes; 17 once `Collection` — the first suffix in the list — comes off.
     */
    protected array $scheduledShipmentCollection = [];

    /**
     * 26 bytes; 19 once `Factory` comes off. `Factory` is the *second* entry in
     * the suffix list, so this one only goes silent if the whole list is read
     * rather than just its head.
     */
    protected ?object $warehouseAdjustmentFactory = null;

    /**
     * 28 bytes; 9 once both `temporary` and `Collection` come off. A prefix and
     * a suffix can be subtracted from the same name.
     */
    protected array $temporaryOrderLineCollection = [];

    /**
     * 31 bytes. It ends in `MockCollection`, but `Collection` matches first and
     * PHPMD stops there — 10 bytes come off rather than 14, leaving 21, which
     * is still over. A subtraction that preferred the longest match, or that
     * did not stop at the first, would leave 17 and silence this line.
     */
    protected array $warehouseAuditLogMockCollection = [];

    /**
     * 42 bytes; 23 once both `temporary` and `Collection` come off, so it stays
     * reported — subtraction shortens a name, it does not exempt one.
     *
     * This is also the prefix-side first-match case. The name starts with both
     * `temporary` and `temporaryWarehouseInventory`, so a prefix loop that
     * carried on past its first hit would take off 9 *and* 29 bytes, leaving 4
     * and silencing the line.
     */
    protected array $temporaryWarehouseInventoryAuditCollection = [];
}

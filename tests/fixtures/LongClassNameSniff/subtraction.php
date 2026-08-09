<?php

declare(strict_types=1);

/**
 * Exercises the subtractPrefixes / subtractSuffixes properties under the four
 * combinations LongClassNameTest drives. Every name is over the maximum before
 * subtraction, so an unconfigured run flags all six lines; the arithmetic noted
 * on each is for prefixes `Abstract` + suffixes `Repository,Factory,MockRepository`.
 */

// 44 bytes, less the `Abstract` prefix (8) = 36. Silent when configured.
abstract class AbstractSupplierContractRenegotiationHandler
{
}

// 46 bytes, less the `Repository` suffix (10) = 36. Silent when configured.
class SupplierContractRenegotiationHandlerRepository
{
}

// 43 bytes, less the `Factory` suffix (7) = 36. `Factory` is the second entry
// of the suffix list, so this only passes if the whole list is read.
class SupplierContractRenegotiationHandlerFactory
{
}

// 51 bytes, less the prefix (8) and the suffix (7) = 36. Both subtractions
// apply to one name, each taken from the original length.
abstract class AbstractSupplierContractRenegotiationHandlerFactory
{
}

// 51 bytes. `Repository` matches before `MockRepository` does and PHPMD stops
// at the first match, so 10 bytes come off, not 14: 41, still over the
// maximum. Subtracting the longer entry instead would silence this line.
class WarehouseInventoryReplenishmentAuditsMockRepository
{
}

// 59 bytes, less the prefix (8) and the `Repository` suffix (10) = 41. Still
// over the maximum: subtraction shortens a name, it does not exempt one.
abstract class AbstractWarehouseInventoryReplenishmentAuditTrailRepository
{
}

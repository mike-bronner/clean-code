<?php

// A shared abstract test case. Unlike the trait and interface beside it this
// *is* a T_CLASS with the `Test` suffix, so only the abstract modifier keeps it
// out — it is exercised through the concrete tests extending it, and lives
// wherever those tests can reach it.

namespace Tests\Feature;

abstract class BillingTest
{
}

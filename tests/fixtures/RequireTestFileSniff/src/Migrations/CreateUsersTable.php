<?php

declare(strict_types=1);

namespace Fixture\Src\Migrations;

/**
 * Framework scaffolding: a concrete, untested class that a consuming project
 * opts out through $excludePatterns rather than by writing a test for it. It
 * is a violation with the shipped (empty) exclude list, and silent once the
 * pattern is configured — both halves are asserted.
 */
class CreateUsersTable
{
    public function up(): void
    {
    }
}

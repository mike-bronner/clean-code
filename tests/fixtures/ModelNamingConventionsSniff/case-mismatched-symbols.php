<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\APIToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Violations that only surface once a symbol is identified the way PHP
 * identifies it: case-insensitively. Every name below is spelled differently
 * from the `use` statement or the framework method it refers to, and PHP treats
 * each as the very same symbol — so reading them with source casing loses the
 * declaration entirely rather than misreading it.
 *
 * The base class is aliased *and* mis-cased, which is the worst of the two
 * losses: matched on casing, `eloquentmodel` resolves to nothing, the class
 * stops looking like a model, and every check in the sniff goes quiet at once.
 */
class Thing extends eloquentmodel
{
    public bool $paid = false;

    /**
     * `ApiToken` and the imported `APIToken` are one symbol — acronym casing
     * drifts in ordinary code. Identified, this returns a model under
     * App\Models and needs the `find` prefix; mis-identified, it resolves to
     * App\Domain\Billing\ApiToken and the check never runs.
     */
    public function fetchApiToken(): ApiToken
    {
        return new APIToken();
    }

    /**
     * The boundary the fix must not cross. Identifying the type folds case, so
     * this *is* known to return `APIToken` — but the model name a `find` method
     * carries is a spelling its author chose, still judged as written, so
     * naming it `ApiToken` does not name the model.
     */
    public function findApiToken(): ApiToken
    {
        return new APIToken();
    }
}

/**
 * Eloquent finds an accessor through method_exists(), which folds case, so both
 * methods below are live legacy accessors — for `foo` and `paid` respectively.
 * Matched with source casing they read as ordinary methods, and nothing else in
 * the ruleset flags them: both are valid PSR-1 camelCase.
 */
class Ledger extends Model
{
    public function getFooattribute(): string
    {
        return 'foo';
    }

    public function setpaidattribute(bool $paid): void
    {
        //
    }
}

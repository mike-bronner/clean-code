<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Response;

class Mailchimp
{
    public function store(): Response
    {
        return response()->noContent();
    }

    public function update(): Response
    {
        return response()->noContent();
    }

    public function setFoo(): string
    {
        return 'foo';
    }

    public function sendReminder(): bool
    {
        return true;
    }

    public function storeDraft(): Response
    {
        return response()->noContent();
    }
}

namespace App\Http\Controllers;

class Webhooks
{
    public function store()
    {
        return response()->noContent();
    }

    protected function update(): Response
    {
        return response()->noContent();
    }

    private function store(): Response
    {
        return response()->noContent();
    }
}

namespace App\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class InvoiceController
{
    public function store(): Response
    {
        return response()->noContent();
    }

    public function update(): Response
    {
        return response()->noContent();
    }
}

class Invoices extends Controller
{
    public function store(): Response
    {
        return response()->noContent();
    }
}

class Payments extends \Illuminate\Routing\Controller
{
    public function update(): Response
    {
        return response()->noContent();
    }
}

class Ledger
{
    public function store(): Response
    {
        return response()->noContent();
    }

    public function update(): Response
    {
        return response()->noContent();
    }
}

class Refunds extends BaseController
{
    public function store(): Response
    {
        return response()->noContent();
    }
}

namespace App\Http\ControllersLegacy;

class Imports
{
    public function store(): Response
    {
        return response()->noContent();
    }
}

namespace App\Http;

class Controllers
{
    public function update(): Response
    {
        return response()->noContent();
    }
}

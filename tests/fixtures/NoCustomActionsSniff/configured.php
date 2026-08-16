<?php

class InvoiceController
{
    public function index(): View
    {
        return view('invoices.index');
    }

    public function middleware(): array
    {
        return [];
    }
}

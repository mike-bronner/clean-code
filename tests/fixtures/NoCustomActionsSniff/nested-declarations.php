<?php

class InventoryController
{
    public function index(): View
    {
        $formatter = new class {
            public function format(array $row): string
            {
                return implode(',', $row);
            }
        };

        return view('inventory.index', ['formatter' => $formatter]);
    }

    public function store(StoreInventoryRequest $request): RedirectResponse
    {
        function normalizeInventoryPath(string $path): string
        {
            return trim($path, '/');
        }

        $slugify = function (string $value): string {
            return strtolower($value);
        };

        return redirect()->route('inventory.index', $slugify($request->name));
    }

    public function reconcile(): RedirectResponse
    {
        return redirect()->route('inventory.index');
    }
}

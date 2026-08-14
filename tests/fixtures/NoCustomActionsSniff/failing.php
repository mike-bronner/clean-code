<?php

class ReportController
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function export(): Response
    {
        return response()->download('report.csv');
    }

    public static function warmCache(): void
    {
    }

    function approve(int $id): void
    {
    }

    protected function formatRow(array $row): array
    {
        return $row;
    }

    private function columns(): array
    {
        return [];
    }
}

abstract class BaseReportController
{
    abstract public function handle(): Response;
}

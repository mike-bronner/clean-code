<?php

class ReportController
{
    public function export(): Response
    {
        return response()->download('report.csv');
    }

    public function
}

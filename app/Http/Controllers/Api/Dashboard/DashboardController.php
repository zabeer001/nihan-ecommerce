<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Dashboard\Services\DashboardSummaryService;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function summary(DashboardSummaryService $service): array
    {
        return $service->handle();
    }
}

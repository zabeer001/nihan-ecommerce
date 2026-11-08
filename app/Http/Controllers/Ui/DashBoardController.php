<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashBoardController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('backend/pages/dashboard/DashBoardPage');
    }
}

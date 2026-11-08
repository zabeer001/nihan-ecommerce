<?php

use App\Http\Controllers\Ui\DashBoardController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/frontend.php';



Route::prefix('dashboard')->group(function () {
    require __DIR__.'/dashboard.php';
});

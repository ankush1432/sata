<?php

use App\Http\Controllers\Admin\AdminPanelWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::prefix('admin')->group(function () {
    Route::get('/', [AdminPanelWebController::class, 'dashboard'])->name('admin.dashboard');
    require base_path('routes/admin.php');
});

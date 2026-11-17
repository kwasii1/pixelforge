<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// No "verified" middleware: the app has no mail provider in production, so requiring a verified
// email would lock every new account out. Account settings in routes/settings.php still require it.
Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', 'generate')->name('dashboard');

    Route::get('generate', [GenerationController::class, 'create'])->name('generations.create');
    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits', [CreditController::class, 'store'])->name('credits.store');

    Route::post('notifications/read', [NotificationController::class, 'markAllAsRead'])->name('notifications.read');

    Route::get('admin/report', ReportController::class)
        ->middleware('can:viewAdminReport')
        ->name('admin.report');
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\PairController;
use Illuminate\Support\Facades\Route;

Route::prefix('__pair')->group(function () {
    Route::get('/',        [PairController::class, 'show'])->name('pair.show');
    Route::post('/',       [PairController::class, 'activate'])->name('pair.activate');
    Route::get('/success', [PairController::class, 'success'])->name('pair.success');
});

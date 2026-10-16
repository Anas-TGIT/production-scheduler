<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/schedule');

Route::resource('orders', OrderController::class)->except(['index', 'show']);

Route::get('/schedule', ScheduleController::class)->name('schedule.index');

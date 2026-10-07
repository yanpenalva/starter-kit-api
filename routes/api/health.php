<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): array => ['status' => 'ok']);
Route::get('/health', HealthController::class);

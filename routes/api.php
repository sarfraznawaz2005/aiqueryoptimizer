<?php

use Illuminate\Support\Facades\Route;
use AIQueryOptimizer\Http\Controllers\OptimizerController;

Route::post('/ai-query-optimizer/analyze', [OptimizerController::class, 'analyze']);
Route::post('/ai-query-optimizer/status', [OptimizerController::class, 'status']);

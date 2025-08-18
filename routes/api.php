<?php

use Illuminate\Support\Facades\Route;
use PackageAIQueryOptimizer\Http\Controllers\OptimizerController;

Route::post('/ai-query-optimizer/analyze', [OptimizerController::class, 'analyze']);

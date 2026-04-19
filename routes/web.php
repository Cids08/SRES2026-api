<?php

use Illuminate\Support\Facades\Route;

Route::get('/api/test', function () {
    return response()->json([
        'message' => 'API is working 🚀'
    ]);
});
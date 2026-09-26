<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/load-balancer-test', function () {
    return response()->json([
        'container' => gethostname(),
        'server_addr' => $_SERVER['SERVER_ADDR'] ?? null,
    ]);
});

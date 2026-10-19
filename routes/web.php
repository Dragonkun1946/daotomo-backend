<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    $indexPath = public_path('index.html');
    if (File::exists($indexPath)) {
        return response()->file($indexPath);
    }
    return view('welcome');
});

Route::get('/index.html', function () {
    return response()->file(public_path('index.html'));
});

// Fallback for HTML pages or SPA routes
Route::fallback(function () {
    $uri = request()->path();

    if (str_starts_with($uri, 'api/')) {
        return response()->json(['success' => false, 'message' => 'API route not found'], 404);
    }

    $filePath = public_path($uri);
    if (File::exists($filePath) && is_file($filePath)) {
        return response()->file($filePath);
    }

    $indexPath = public_path('index.html');
    if (File::exists($indexPath)) {
        return response()->file($indexPath);
    }

    return response('Page not found', 404);
});

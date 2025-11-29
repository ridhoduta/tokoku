<?php
use Illuminate\Support\Facades\Route;

// Auto redirect pertama kali ke /login (React)
Route::get('/', function () {
    return redirect('/login');
});

// Catch-all route untuk React SPA
Route::get('/{any}', function () {
    return file_get_contents(public_path('react/index.html'));
})->where('any', '^(?!react/).*$');

Route::get('/test-env', function () {
    return env('FIREBASE_PROJECT_ID');
});

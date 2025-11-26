<?php
use Illuminate\Support\Facades\Route;

// Serve React for root
Route::get('/', function () {
    return file_get_contents(public_path('react/index.html'));
});

// Catch-all untuk SPA, tapi jangan ganggu folder react/
Route::get('/{any}', function () {
    return file_get_contents(public_path('react/index.html'));
})->where('any', '^(?!react/).*$');

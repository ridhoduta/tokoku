<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\KategoriController;

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);

// 🔹 Read-only (index, show) untuk semua role
Route::middleware(['jwt.verify:R001,R002'])->group(function () {
    Route::apiResource('barang', BarangController::class)->only(['index', 'show']);
    Route::apiResource('kategori', KategoriController::class)->only(['index', 'show']);
});

// 🔹 CRUD (store, update, destroy) hanya untuk Admin (R001)
Route::middleware(['jwt.verify:R001'])->group(function () {
    Route::apiResource('barang', BarangController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('kategori', KategoriController::class)->only(['store', 'update', 'destroy']);
});




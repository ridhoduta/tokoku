<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\WhatsApp;

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);
Route::get('/send-wa', [WhatsApp::class, 'sendMessage']);

// 🔹 Read-only (index, show) untuk semua role
Route::middleware(['jwt.verify:R001,R002'])->group(function () {
    Route::apiResource('barang', BarangController::class)->only(['index', 'show']);
    Route::apiResource('kategori', KategoriController::class)->only(['index', 'show']);
    Route::apiResource('pesanan', PesananController::class)->only(['index', 'show']);
    Route::post('/pesanan/bayar', [PesananController::class, 'bayar']);
    Route::get('/barang/kategori/{kategori_id}', [BarangController::class, 'getByKategori']);

});

// 🔹 CRUD (store, update, destroy) hanya untuk Admin (R001)
Route::middleware(['jwt.verify:R001'])->group(function () {
    Route::apiResource('barang', BarangController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('kategori', KategoriController::class)->only(['store', 'update', 'destroy']);
    Route::put('/pesanan/{id}', [PesananController::class, 'update']);
    Route::get('/laporan', [PesananController::class, 'getLaporan']);
});
// 🔹 User (R002): bisa lihat, tambah, dan hapus pesanan miliknya
Route::middleware(['jwt.verify:R002'])->group(function () {
    Route::post('/pesanan', [PesananController::class, 'store']);
    Route::delete('/pesanan/{id}', [PesananController::class, 'destroy']);
});






Route::post('/send-fcm', [NotifikasiController::class, 'sendFCM']);







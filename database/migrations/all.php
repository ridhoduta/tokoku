<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('nama_role');
            $table->timestamps();
        });

        // users
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('nama');
            $table->string('alamat')->nullable();
            $table->string('no_hp');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('role_id')->references('role_id')->on('roles')->onDelete('cascade');
        });

        // kategori
        Schema::create('kategori', function (Blueprint $table) {
            $table->id('kategori_id');
            $table->string('nama_kategori');
            $table->timestamps();
        });

        // produk
        Schema::create('produk', function (Blueprint $table) {
            $table->id('produk_id');
            $table->string('nama');
            $table->decimal('harga', 12, 2);
            $table->integer('stok');
            $table->unsignedBigInteger('kategori_id');
            $table->timestamps();

            $table->foreign('kategori_id')->references('kategori_id')->on('kategori')->onDelete('cascade');
        });

        // status_pesanan
        Schema::create('status_pesanan', function (Blueprint $table) {
            $table->id('status_id');
            $table->string('kode');
            $table->string('label');
            $table->string('allowed_metode')->nullable();
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        // pesanan
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id('pesanan_id');
            $table->unsignedBigInteger('user_id');
            $table->string('metode')->nullable();
            $table->dateTime('tanggal_pemesanan');
            $table->decimal('total', 12, 2);
            $table->unsignedBigInteger('status_id');
            $table->string('status_pembayaran')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('status_id')->references('status_id')->on('status_pesanan')->onDelete('cascade');
        });

        // pesanan_item
        Schema::create('pesanan_item', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('pesanan_id');
            $table->unsignedBigInteger('produk_id');
            $table->integer('qty');
            $table->decimal('harga_saat_beli', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->foreign('pesanan_id')->references('pesanan_id')->on('pesanan')->onDelete('cascade');
            $table->foreign('produk_id')->references('produk_id')->on('produk')->onDelete('cascade');
        });

        // pembayaran
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('pembayaran_id');
            $table->unsignedBigInteger('pesanan_id');
            $table->string('provider');
            $table->string('metode');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('transaction_ref')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('pesanan_id')->references('pesanan_id')->on('pesanan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('pesanan_item');
        Schema::dropIfExists('pesanan');
        Schema::dropIfExists('status_pesanan');
        Schema::dropIfExists('produk');
        Schema::dropIfExists('kategori');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};

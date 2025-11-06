<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Exceptions\JWTException;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    protected $firestore;

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }


    // ✅ Register
    public function register(Request $request)
    {
        $request->validate([
            'nama'      => 'required',
            'alamat'    => 'required',
            'nomor_hp'  => 'required',
            'password'  => 'required|min:6',
            'role_id'   => 'required',
        ]);

        // Generate user_id otomatis: US + 5 digit angka random
        $randomNumber = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $user_id = "US" . $randomNumber;

        // Pastikan user_id unik
        $userRef = $this->firestore->collection('users')->document($user_id);
        while ($userRef->snapshot()->exists()) {
            $randomNumber = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $user_id = "US" . $randomNumber;
            $userRef = $this->firestore->collection('users')->document($user_id);
        }

        $userData = [
            'user_id'   => $user_id,
            'nama'      => $request->nama,
            'alamat'    => $request->alamat,
            'nomor_hp'  => $request->nomor_hp,
            'password_hash'  => Hash::make($request->password),
            'role_id'   => $request->role_id,
        ];

        $userRef->set($userData);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data'    => $userData
        ]);
    }

    // ✅ Login
    public function login(Request $request)
    {
        $request->validate([
            'nomor_hp'  => 'required',
            'password'  => 'required',
        ]);

        // cari user berdasarkan nomor_hp
        $query = $this->firestore
            ->collection('users')
            ->where('nomor_hp', '=', $request->nomor_hp)
            ->documents();

        if ($query->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        // ambil user pertama
        $userSnapshot = $query->rows()[0];
        $userData     = $userSnapshot->data();
        // dd($userData);

        // validasi password
        if (!Hash::check($request->password, $userData['password_hash'])) {
            return response()->json(['success' => false, 'message' => 'Password salah'], 401);
        }

        try {
            $customClaims = [
                'sub'     => (string) $userData['user_id'],
                'user_id' => $userData['user_id'],
                'nama'    => $userData['nama'],
                'alamat'    => $userData['alamat'],
                'nomor_hp'    => $userData['nomor_hp'],
                'role_id' => $userData['role_id'],
                'iat'     => time(),
                'exp'     => time() + 3600,
            ];

            $payload = JWTFactory::customClaims($customClaims)->make();
            $token   = JWTAuth::encode($payload)->get();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat token',
                'error'   => $e->getMessage(),
                // 'data' => $userData
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token'   => $token,
            'data'    => $customClaims
        ]);
    }



    // ✅ Ambil user dari token
    public function logout(Request $request)
    {
        try {
            // Ambil token dari header Authorization
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan'
                ], 400);
            }

            // Invalidate token supaya tidak bisa dipakai lagi
            JWTAuth::invalidate($token);

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil, token sudah invalid'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal logout',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

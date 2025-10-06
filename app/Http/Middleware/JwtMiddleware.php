<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Request;

class JwtMiddleware
{
     public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            // Ambil payload dari token (tanpa query ke database)
            $payload = JWTAuth::parseToken()->getPayload();
        } catch (TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token sudah kadaluarsa'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid'], 401);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Token tidak ditemukan'], 401);
        }

        // Ambil data dari payload token
        $roleId  = $payload->get('role_id');
        $userId  = $payload->get('user_id');
        $userNama = $payload->get('nama');

        // Kalau ada parameter role → cek apakah cocok
        if (!empty($roles) && !in_array($roleId, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak, role tidak sesuai',
                'required_roles' => $roles,
                'your_role' => $roleId
            ], 403);
        }

        // Bisa diteruskan, simpan info user di request biar gampang dipakai di controller
        $request->merge([
            'auth_user_id' => $userId,
            'auth_role_id' => $roleId,
            'auth_nama'    => $userNama,
        ]);

        return $next($request);
    }
}

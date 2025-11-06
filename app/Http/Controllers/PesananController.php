<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\FacadesLog;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Client;


class PesananController extends Controller
{
    protected $firestore;

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * 🔹 INDEX — Menampilkan daftar pesanan
     */
    public function index()
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $role_id = $payload->get('role_id');
        $nama_pemesan = $payload->get('nama');

        $pesananRef = $this->firestore->collection('pesanan');

        if ($role_id === 'R001') {
            $docs = $pesananRef->documents();
        } else {
            $docs = $pesananRef->where('nama_pemesan', '=', $nama_pemesan)->documents();
        }

        $data = [];
        foreach ($docs as $doc) {
            if ($doc->exists()) {
                $data[] = $doc->data();
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 🔹 STORE — Tambah pesanan baru
     */
    public function store(Request $request)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $role_id = $payload->get('role_id');
        $nama_pemesan = $payload->get('nama');

        if ($role_id !== 'R002') {
            return response()->json(['success' => false, 'message' => 'Hanya user yang bisa membuat pesanan'], 403);
        }

        $request->validate([
            'alamat' => 'required|string',
            'pengiriman' => 'required|string',
            'pembayaran' => 'required|string',
            'barang_dipesan' => 'required|array|min:1',
            'barang_dipesan.*.barang_id' => 'required|string',
            'barang_dipesan.*.nama_barang' => 'required|string',
            'barang_dipesan.*.harga_barang' => 'required|numeric|min:0',
            'barang_dipesan.*.qty' => 'required|integer|min:1',
            'barang_dipesan.*.gambar_barang' => 'required|string', // ✅ letakkan di sini
            'uid' => 'nullable|string',
        ]);

        // Hitung total harga
        $total = 0;
        foreach ($request->barang_dipesan as $item) {
            $total += $item['harga_barang'] * $item['qty'];
        }

        // Generate ID unik
        $id = 'P' . rand(100, 999) . rand(10, 99);

        $data = [
            'id' => $id,
            'nama_pemesan' => $nama_pemesan,
            'alamat' => $request->alamat,
            'barang_dipesan' => $request->barang_dipesan,
            'total_harga' => $total,
            'status' => 'menunggu',
            'pengiriman' => $request->pengiriman,
            'pembayaran' => $request->pembayaran,
            'status_pembayaran' => 'belum dibayar',
            'tanggal' => Carbon::now()->format('d-m-Y'),
            'uid' => $id,
        ];

        $this->firestore->collection('pesanan')->document($id)->set($data);

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat',
            'data' => $data
        ], 201);
    }


    /**
     * 🔹 SHOW — Detail pesanan
     */
    public function show($id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $role_id = $payload->get('role_id');
        $nama_pemesan = $payload->get('nama');

        $doc = $this->firestore->collection('pesanan')->document($id)->snapshot();

        if (!$doc->exists()) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        $data = $doc->data();

        if ($role_id === 'R002' && $data['nama_pemesan'] !== $nama_pemesan) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa melihat pesanan orang lain'], 403);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 🔹 UPDATE — Ubah status (hanya admin)
     */


    public function update(Request $request, $id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $role_id = $payload->get('role_id');
        // $noHp = $payload->get('nomor_hp');

        if ($role_id !== 'R001') {
            return response()->json(['success' => false, 'message' => 'Hanya admin yang bisa mengubah status'], 403);
        }

        $request->validate([
            'status' => 'required|string'
        ]);

        $docRef = $this->firestore->collection('pesanan')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        $data = $snapshot->data();

        // Update status di Firestore
        $docRef->update([['path' => 'status', 'value' => $request->status]]);

        try {
            $uid = $data['uid'] ?? null;
            if (!$uid) {
                Log::warning("Pesanan {$id} tidak memiliki uid.");
                return response()->json(['success' => true, 'message' => 'Status diperbarui tanpa notifikasi (tidak ada uid)']);
            }

            // Cari user berdasarkan UID
            $userQuery = $this->firestore->collection('users')
                ->where('user_id', '=', $uid)
                ->limit(1)
                ->documents();

            if ($userQuery->isEmpty()) {
                Log::warning("User dengan uid {$uid} tidak ditemukan di Firestore.");
                return response()->json(['success' => true, 'message' => 'Status diperbarui tanpa notifikasi (user tidak ditemukan)']);
            }

            $userSnap = $userQuery->rows()[0];
            $userData = $userSnap->data();
            $fcmToken = trim($userData['token'] ?? '');
            $no_hp = trim($userData['nomor_hp'] ?? ''); // pastikan user punya field no_hp di Firestore

            // ============ 🔔 Kirim Notifikasi FCM ============
            if (!empty($fcmToken)) {
                $serviceAccountPath = storage_path('app/firebase/serviceAccount.json');
                $client = new Client();
                $client->setAuthConfig($serviceAccountPath);
                $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

                $tokenArray = $client->fetchAccessTokenWithAssertion();
                if (!isset($tokenArray['error'])) {
                    $accessToken = $tokenArray['access_token'];
                    $projectId = json_decode(file_get_contents($serviceAccountPath), true)['project_id'];
                    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                    $body = [
                        "message" => [
                            "token" => $fcmToken,
                            "data" => [
                                "title" => "Status Pesanan Diperbarui",
                                "body" => "Pesanan #{$id} sekarang berstatus: {$request->status}",
                                "pesananId" => $id,
                                "from_notification" => "true"
                            ]
                        ]
                    ];

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json',
                    ])->post($url, $body);

                    Log::info('FCM Response: ' . $response->body());
                }
            }

            // ============ 💬 Kirim Pesan WhatsApp via Fonnte ============
            if (strtolower($request->status) === 'selesai' && !empty($no_hp)) {
                try {
                    $message = "✅ *Pesanan Anda Sudah Selesai*\n\n" .
                        "Halo *{$data['nama_pemesan']}*,\n" .
                        "Pesanan dengan ID *{$id}* telah *selesai diproses*.\n\n" .
                        "📦 *Detail Pesanan:*\n" .
                        collect($data['barang_dipesan'])->map(function ($item) {
                            return "- {$item['nama_barang']} ({$item['qty']} x Rp" . number_format($item['harga_barang'], 0, ',', '.') . ")";
                        })->implode("\n") .
                        "\n\n💰 *Total: Rp" . number_format($data['total_harga'], 0, ',', '.') . "*\n" .
                        "Terima kasih telah berbelanja bersama kami 🙏";

                    $fonnteResponse = Http::withHeaders([
                        'Authorization' => env('FONNTE_API_KEY')
                    ])->post(env('FONNTE_API_URL', 'https://api.fonnte.com/send'), [
                        'target' => $no_hp,
                        'message' => $message
                    ]);

                    Log::info("Fonnte Response: " . $fonnteResponse->body());
                } catch (\Exception $e) {
                    Log::error("Gagal mengirim WA lewat Fonnte: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Gagal memproses update pesanan: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Status pesanan diperbarui dan notifikasi (jika ada) dikirim']);
    }





    /**
     * 🔹 DESTROY — Hapus pesanan
     * - User hanya boleh hapus miliknya
     * - Admin boleh hapus semua
     */
    public function destroy($id)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $role_id = $payload->get('role_id');
        $nama_pemesan = $payload->get('nama');

        $docRef = $this->firestore->collection('pesanan')->document($id);
        $snapshot = $docRef->snapshot();

        if (!$snapshot->exists()) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        $data = $snapshot->data();

        if ($role_id === 'R002' && $data['nama_pemesan'] !== $nama_pemesan) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus pesanan orang lain'], 403);
        }

        $docRef->delete();

        return response()->json(['success' => true, 'message' => 'Pesanan berhasil dihapus']);
    }
    /**
     * 🔹 BAYAR — Proses pembayaran menggunakan Midtrans Sandbox
     */
    public function bayar(Request $request)
    {
        $request->validate([
            'pesananId' => 'required|string',
            'nama_pemesan' => 'required|string',
            'tanggal' => 'required|string',
            'pengiriman' => 'required|string',
        ]);
        $payload = JWTAuth::parseToken()->getPayload();
        $email = $payload->get('email');

        // 🔹 Ambil data pesanan dari Firestore
        $pesananRef = $this->firestore->collection('pesanan')->document($request->pesananId);
        $pesananSnap = $pesananRef->snapshot();

        if (!$pesananSnap->exists()) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
        }

        $pesananData = $pesananSnap->data();
        $total = $pesananData['total_harga'];

        // 🔹 Konfigurasi Midtrans
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false; // Sandbox
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // 🔹 Parameter transaksi Midtrans
        $params = [
            'transaction_details' => [
                'order_id' => 'INV-' . $request->pesananId . '-' . time(),
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $request->nama_pemesan,
                'email' => $email ?? 'user@example.com',
            ],
            'item_details' => array_map(function ($item) {
                return [
                    'id' => $item['barang_id'],
                    'price' => $item['harga_barang'],
                    'quantity' => $item['qty'],
                    'name' => $item['nama_barang'],
                ];
            }, $pesananData['barang_dipesan']),
        ];

        try {
            // 🔹 Dapatkan Snap Token
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // 🔹 Simpan data ke Firestore (collection "pembayaran")
            $pembayaranId = 'PB' . rand(1000, 9999);
            $pembayaranData = [
                'id' => $pembayaranId,
                'pesanan_id' => $request->pesananId,
                'nama_pemesan' => $request->nama_pemesan,
                'tanggal' => $request->tanggal,
                'pengiriman' => $request->pengiriman,
                'total' => $total,
                'status' => 'dibayar',
                'snap_token' => $snapToken,
            ];

            $this->firestore->collection('pembayaran')->document($pembayaranId)->set($pembayaranData);

            // 🔹 Update status_pembayaran pada pesanan
            $pesananRef->update([
                ['path' => 'status_pembayaran', 'value' => 'dibayar'],
                ['path' => 'tanggal_pembayaran', 'value' => date('Y-m-d H:i:s')],
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil diproses',
                'snap_token' => $snapToken,
                'data' => $pembayaranData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }
}

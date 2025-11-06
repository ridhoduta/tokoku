<?php

namespace App\Http\Controllers;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KategoriController extends Controller
{
    protected $firestore;
    protected $collection = 'kategori';

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    // GET /api/kategori
    public function index()
    {
        try {
            $documents = $this->firestore->collection($this->collection)->documents();
            $kategori = [];

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $kategori[] = [
                        'id' => $document->id(),
                        'data' => $document->data()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Data kategori berhasil diambil',
                'data' => $kategori
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kategori',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // POST /api/kategori
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kategori' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Ambil semua dokumen untuk hitung jumlah kategori yang sudah ada
            $documents = $this->firestore->collection($this->collection)->documents();
            $count = 0;

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $count++;
                }
            }

            // Buat ID baru berdasarkan jumlah (K001, K002, dst)
            $newId = 'K' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            $data = [
                'kategori_id'   => $newId,
                'nama_kategori' => $request->nama_kategori,
            ];

            // Simpan ke Firestore dengan document ID = $newId
            $docRef = $this->firestore->collection($this->collection)->document($newId);
            $docRef->set($data);

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil ditambahkan',
                'data'    => $data
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan kategori',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // GET /api/kategori/{id}
    public function show($id)
    {
        $doc = $this->firestore->collection($this->collection)->document($id)->snapshot();

        if (!$doc->exists()) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        return response()->json([
            'id' => $doc->id(),
            'data' => $doc->data()
        ]);
    }

    // PUT /api/kategori/{id}
    public function update(Request $request, $id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (!$docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_kategori' => 'sometimes|required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $docRef->set($request->all(), ['merge' => true]);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui',
            'data'    => $request->all()
        ]);
    }

    // DELETE /api/kategori/{id}
    public function destroy($id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (!$docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        $docRef->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}

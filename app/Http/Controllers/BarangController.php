<?php

namespace App\Http\Controllers;

use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BarangController extends Controller
{
    protected $firestore;
    protected $collection = 'barang';
    protected $collectionKategori = 'kategori';


    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    // GET /api/barang
    public function index()
    {
        $documents = $this->firestore->collection($this->collection)->documents();
        $barang = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $barang[] = [
                    'id' => $document->id(),
                    'data' => $document->data()
                ];
            }
        }

        return response()->json($barang);
    }
    

    // POST /api/barang
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang'   => 'required|string|max:255',
            'harga_barang'  => 'required|numeric',
            'stok_barang'   => 'required|integer',
            'kategori_id'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate barang_id random (misalnya B + 6 digit angka random)
        $barang_id = 'B' . mt_rand(100000, 999999);

        $data = [
            'barang_id'    => $barang_id,
            'nama_barang'  => $request->nama_barang,
            'harga_barang' => (int) $request->harga_barang,
            'stok_barang'  => (int) $request->stok_barang,
            'kategori_id'  => $request->kategori_id,
        ];

        $docRef = $this->firestore->collection($this->collection)
            ->document($barang_id);

        $docRef->set($data);

        return response()->json([
            'success' => true,
            'data'    => $data
        ], 201);
    }



    // GET /api/barang/{id}
    public function show($id)
    {
        $doc = $this->firestore->collection($this->collection)->document($id)->snapshot();

        if (!$doc->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        return response()->json([
            'id' => $doc->id(),
            'data' => $doc->data()
        ]);
    }

    // PUT /api/barang/{id}
    public function update(Request $request, $id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (!$docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_barang'   => 'sometimes|required|string|max:255',
            'harga_barang'  => 'sometimes|required|numeric',
            'stok_barang'   => 'sometimes|required|integer',
            'kategori_id'   => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // $docRef->set($request->all(), ['merge' => true]);
        $docRef->set($request->all());

        return response()->json([
            'success' => true
        ], 201);
    }

    // DELETE /api/barang/{id}
    public function destroy($id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (!$docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        $docRef->delete();

        return response()->json([
            'success' => true
        ], 201);
    }
}

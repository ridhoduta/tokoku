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
                    'data' => $document->data(),
                ];
            }
        }

        return response()->json($barang);
    }

    public function getByKategori($kategori_id)
    {
        // Query langsung berdasarkan field kategori_id (string)
        $query = $this->firestore
            ->collection($this->collection)
            ->where('kategori_id', '=', $kategori_id)
            ->documents();

        $barang = [];

        foreach ($query as $document) {
            if ($document->exists()) {
                $barang[] = [
                    'id' => $document->id(),
                    'data' => $document->data(),
                ];
            }
        }

        return response()->json($barang);
    }

    // POST /api/barang
    // POST /api/barang
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang' => 'required|string|max:255',
            'harga_barang' => 'required_if:satuan_utama,pcs|numeric|nullable',
            'stok_barang' => 'required|integer',
            'kategori_id' => 'required|string',
            'satuan_utama' => 'required|string|in:pcs,dus,kg',

            // khusus dus
            'isi_per_dus' => 'required_if:satuan_utama,dus|integer|nullable',
            'harga_dus' => 'required_if:satuan_utama,dus|numeric|nullable',
            'harga_pcs' => 'required_if:satuan_utama,dus|numeric|nullable',

            // khusus kg
            'harga_per_kg' => 'required_if:satuan_utama,kg|numeric|nullable',
            'harga_per_500g' => 'required_if:satuan_utama,kg|numeric|nullable',
            'harga_per_250g' => 'required_if:satuan_utama,kg|numeric|nullable',

            'gambar_barang' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $barang_id = 'B'.mt_rand(100000, 999999);

        $gambarUrl = null;
        if ($request->hasFile('gambar_barang')) {
            $path = $request->file('gambar_barang')->store('barang', 'public');
            $gambarUrl = asset('storage/'.$path);
        }

        $satuanUtama = $request->satuan_utama;

        // DATA DASAR
        $data = [
            'barang_id' => $barang_id,
            'nama_barang' => $request->nama_barang,
            'harga_barang' => (int) $request->harga_barang, // harga dasar pcs
            'stok_barang' => (int) $request->stok_barang,
            'kategori_id' => $request->kategori_id,
            'gambar_barang' => $gambarUrl,
            'satuan_utama' => $satuanUtama,
        ];

        // SATUAN DUS
        if ($satuanUtama === 'dus') {
            $data['isi_per_dus'] = (int) $request->isi_per_dus;
            $data['harga_dus'] = (int) $request->harga_dus;
            $data['harga_pcs'] = (int) $request->harga_pcs;
        }

        // SATUAN KG
        if ($satuanUtama === 'kg') {
            $data['punya_turunan_kg'] = true;
            $data['stok_dalam_gram'] = (int) $request->stok_barang * 1000;

            $data['harga_per_kg'] = (int) $request->harga_per_kg;
            $data['harga_per_500g'] = (int) $request->harga_per_500g;
            $data['harga_per_250g'] = (int) $request->harga_per_250g;
        }

        $this->firestore->collection($this->collection)
            ->document($barang_id)
            ->set($data);

        return response()->json([
            'success' => true,
            'message' => 'Barang berhasil ditambahkan',
            'data' => $data,
        ], 201);
    }

    // GET /api/barang/{id}
    public function show($id)
    {
        $doc = $this->firestore->collection($this->collection)->document($id)->snapshot();

        if (! $doc->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        $data = $doc->data();

        // Auto generate satuan turunan jika satuan utama adalah kg
        if (isset($data['satuan_utama']) && $data['satuan_utama'] === 'kg') {
            $data['satuan_turunan'] = [
                ['unit' => '1kg', 'gram' => 1000],
                ['unit' => '500g', 'gram' => 500],
                ['unit' => '250g', 'gram' => 250],
            ];
        }

        return response()->json([
            'id' => $doc->id(),
            'data' => $data,
        ]);
    }

    // PUT /api/barang/{id}
    public function update(Request $request, $id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (! $docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_barang' => 'sometimes|required|string|max:255',
            'harga_barang' => 'sometimes|required|numeric',
            'stok_barang' => 'sometimes|required|integer',
            'kategori_id' => 'sometimes|required|string',
            'isi_per_dus' => 'sometimes|required_if:satuan_utama,dus|integer|nullable',
            'satuan_utama' => 'sometimes|string|in:pcs,dus,kg',
        ]);
        if ($request->satuan_utama === 'kg' && $request->stok_barang) {
            $request->merge([
                'stok_dalam_gram' => $request->stok_barang * 1000,
                'punya_turunan_kg' => true,
            ]);
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // $docRef->set($request->all(), ['merge' => true]);
        $docRef->set($request->all());

        return response()->json([
            'success' => true,
        ], 201);
    }

    // DELETE /api/barang/{id}
    public function destroy($id)
    {
        $docRef = $this->firestore->collection($this->collection)->document($id);

        if (! $docRef->snapshot()->exists()) {
            return response()->json(['message' => 'Barang tidak ditemukan'], 404);
        }

        $docRef->delete();

        return response()->json([
            'success' => true,
        ], 201);
    }
}

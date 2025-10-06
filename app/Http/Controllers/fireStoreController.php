<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Cloud\Firestore\FirestoreClient;

class fireStoreController extends Controller
{
    protected $firestore;
    protected $collection = 'barang';

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    public function addBarang(Request $request)
    {
        $data = [
            'barang_id'   => $request->input('barang_id'),
            'nama_barang' => $request->input('nama_barang'),
            'harga_barang'=> (int) $request->input('harga_barang'),
        ];

        // Simpan dengan ID otomatis
        $this->firestore->collection($this->collection)->add($data);

        return response()->json([
            'message' => 'Barang berhasil ditambahkan',
            'data'    => $data,
        ]);
    }

    public function getBarang()
    {
        $documents = $this->firestore->collection($this->collection)->documents();
        $data = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $data[] = [
                    'id'   => $document->id(),   // ID dokumen di Firestore
                    'data' => $document->data(), // Data barang
                ];
            }
        }

        return response()->json($data);
    }

}

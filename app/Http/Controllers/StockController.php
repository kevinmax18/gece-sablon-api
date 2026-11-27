<?php

namespace App\Http\Controllers;

use App\Models\StockItem;
use Illuminate\Http\Request;

/** Kelola stok: Admin dan Owner. */
class StockController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => StockItem::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'unit'         => 'required|string|max:20',
            'quantity'     => 'required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
        ]);

        $item = StockItem::create($data + ['updated_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Stok ditambahkan.', 'data' => $item], 201);
    }

    public function update(Request $request, int $id)
    {
        $item = StockItem::findOrFail($id);

        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'unit'         => 'sometimes|string|max:20',
            'quantity'     => 'sometimes|numeric|min:0',
            'min_quantity' => 'sometimes|numeric|min:0',
        ]);

        $item->update($data + ['updated_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Stok diperbarui.', 'data' => $item]);
    }

    public function destroy(int $id)
    {
        StockItem::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Stok dihapus.']);
    }
}

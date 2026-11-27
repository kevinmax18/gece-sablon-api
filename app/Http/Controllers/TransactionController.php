<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

/** Keuangan: HANYA Owner (dijaga middleware role:owner di rute). */
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $q = Transaction::query();

        if ($request->filled('from')) $q->whereDate('trx_date', '>=', $request->query('from'));
        if ($request->filled('to'))   $q->whereDate('trx_date', '<=', $request->query('to'));
        if ($request->filled('type')) $q->where('type', $request->query('type'));

        return response()->json(['success' => true, 'data' => $q->orderByDesc('trx_date')->orderByDesc('id')->paginate(30)]);
    }

    /** Ringkasan laba/rugi per periode: ?from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function summary(Request $request)
    {
        $q = Transaction::query();

        if ($request->filled('from')) $q->whereDate('trx_date', '>=', $request->query('from'));
        if ($request->filled('to'))   $q->whereDate('trx_date', '<=', $request->query('to'));

        $income  = (int) (clone $q)->where('type', 'income')->sum('amount');
        $expense = (int) (clone $q)->where('type', 'expense')->sum('amount');

        return response()->json(['success' => true, 'data' => [
            'income'  => $income,
            'expense' => $expense,
            'profit'  => $income - $expense,
        ]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:income,expense',
            'category'    => 'required|string|max:50',
            'amount'      => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'trx_date'    => 'required|date',
        ]);

        $trx = Transaction::create($data + ['source' => 'manual', 'created_by' => $request->user()->id]);

        return response()->json(['success' => true, 'message' => 'Transaksi dicatat.', 'data' => $trx], 201);
    }

    public function update(Request $request, int $id)
    {
        $trx = Transaction::findOrFail($id);

        if ($trx->source === 'auto') {
            return response()->json(['success' => false, 'message' => 'Transaksi otomatis tidak bisa diubah.'], 422);
        }

        $data = $request->validate([
            'type'        => 'sometimes|in:income,expense',
            'category'    => 'sometimes|string|max:50',
            'amount'      => 'sometimes|integer|min:1',
            'description' => 'nullable|string|max:255',
            'trx_date'    => 'sometimes|date',
        ]);

        $trx->update($data);

        return response()->json(['success' => true, 'message' => 'Transaksi diperbarui.', 'data' => $trx]);
    }

    public function destroy(int $id)
    {
        $trx = Transaction::findOrFail($id);

        if ($trx->source === 'auto') {
            return response()->json(['success' => false, 'message' => 'Transaksi otomatis tidak bisa dihapus.'], 422);
        }

        $trx->delete();

        return response()->json(['success' => true, 'message' => 'Transaksi dihapus.']);
    }
}

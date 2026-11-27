<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const STATUSES = ['DITERIMA', 'SEDANG_DIPROSES', 'CETAK_DTF', 'PRESSING', 'SIAP_DIAMBIL', 'SELESAI', 'DIBATALKAN'];
    private const STAGES   = ['DESAIN_MOCKUP', 'PENGADAAN_BAJU', 'CETAK_DTF_MATERIAL', 'PENYABLONAN', 'PEMERIKSAAN_QC'];

    /** Pelanggan: pesanan miliknya. Admin/Owner: semua pesanan (filter ?status=). */
    public function index(Request $request)
    {
        $user = $request->user();
        $q = Order::with('items')->orderByDesc('created_at');

        if ($user->role === 'customer') {
            $q->where('user_id', $user->id);
        } elseif ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        return response()->json(['success' => true, 'data' => $q->paginate(20)]);
    }

    public function show(Request $request, Order $order)
    {
        if ($request->user()->role === 'customer' && $order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $order->load(['items', 'stages', 'logs'])]);
    }

    /**
     * Membuat pesanan. Satu pesanan = satu produk (sesuai spesifikasi).
     * Harga dihitung di SERVER: harga dasar + tambahan ukuran (+ tambahan bahan bila material_id dikirim).
     */
    public function store(Request $request)
    {
        $user   = $request->user();
        $manual = in_array($user->role, ['admin', 'owner'], true);

        $data = $request->validate([
            'product_id'     => 'required|integer|exists:products,id',
            'color_id'       => 'required|integer|exists:product_colors,id',
            'size_id'        => 'required|integer|exists:product_sizes,id',
            'material_id'    => 'nullable|integer|exists:product_materials,id',
            'quantity'       => 'required|integer|min:1|max:999',
            'notes'          => 'nullable|string|max:500',
            'design'         => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
            'customer_name'  => $manual ? 'required|string|max:100' : 'nullable',
            'customer_phone' => $manual ? 'required|string|max:20' : 'nullable',
        ]);

        if (empty($data['notes']) && ! $request->hasFile('design')) {
            return response()->json([
                'success' => false,
                'message' => 'Isi catatan atau unggah file desain.',
            ], 422);
        }

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);
        $color   = $product->colors()->find($data['color_id']);
        $size    = $product->sizes()->find($data['size_id']);
        $mat     = isset($data['material_id']) ? $product->materials()->find($data['material_id']) : null;

        if (! $color || ! $size || (isset($data['material_id']) && ! $mat)) {
            return response()->json([
                'success' => false,
                'message' => 'Warna, ukuran, atau bahan tidak sesuai dengan produk.',
            ], 422);
        }

        $unitPrice = $product->base_price + $size->extra_price + ($mat->extra_price ?? 0);
        $designPath = $request->hasFile('design') ? $request->file('design')->store('designs', 'public') : null;

        $order = DB::transaction(function () use ($user, $manual, $data, $product, $color, $size, $mat, $unitPrice, $designPath) {
            $prefix = 'GEC-'.now()->format('Ymd').'-';
            $next   = Order::where('code', 'like', $prefix.'%')->lockForUpdate()->count() + 1;

            $order = Order::create([
                'code'           => $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
                'user_id'        => $manual ? null : $user->id,
                'created_via'    => $manual ? 'manual' : 'customer',
                'created_by'     => $user->id,
                'customer_name'  => $manual ? $data['customer_name'] : $user->name,
                'customer_phone' => $manual ? $data['customer_phone'] : $user->phone,
                'status'         => 'DITERIMA',
                'total_price'    => $unitPrice * $data['quantity'],
                'notes'          => $data['notes'] ?? null,
                'design_path'    => $designPath,
            ]);

            $order->items()->create([
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'material_name' => $mat->name ?? null,
                'color_name'    => $color->name,
                'size_label'    => $size->label,
                'quantity'      => $data['quantity'],
                'unit_price'    => $unitPrice,
            ]);

            foreach (self::STAGES as $i => $stage) {
                $order->stages()->create(['stage' => $stage, 'seq' => $i + 1, 'is_done' => false]);
            }

            $this->log($order, $user->name, $manual ? 'Pesanan manual dibuat' : 'Pesanan dikirim');

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dikirim.',
            'data'    => $order->load(['items', 'stages']),
        ], 201);
    }

    /** Pelanggan boleh membatalkan hanya saat status DITERIMA. */
    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
        }
        if ($order->status !== 'DITERIMA') {
            return response()->json(['success' => false, 'message' => 'Pesanan sudah diproses dan tidak bisa dibatalkan.'], 422);
        }

        $order->update(['status' => 'DIBATALKAN']);
        $this->log($order, $request->user()->name, 'Pesanan dibatalkan pelanggan');

        return response()->json(['success' => true, 'message' => 'Pesanan dibatalkan.', 'data' => $order]);
    }

    /** Admin/Owner: ubah status. Saat SELESAI, pemasukan tercatat otomatis (sekali saja). */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => 'required|in:'.implode(',', self::STATUSES)]);

        if (in_array($order->status, ['SELESAI', 'DIBATALKAN'], true)) {
            return response()->json(['success' => false, 'message' => 'Pesanan sudah final.'], 422);
        }

        DB::transaction(function () use ($order, $data, $request) {
            $order->update(['status' => $data['status']]);
            $this->log($order, $request->user()->name, 'Status diubah menjadi '.$data['status']);

            if ($data['status'] === 'SELESAI') {
                Transaction::firstOrCreate(
                    ['order_id' => $order->id, 'source' => 'auto', 'type' => 'income'],
                    [
                        'category'    => 'Penjualan',
                        'amount'      => $order->total_price,
                        'description' => 'Pesanan '.$order->code,
                        'trx_date'    => now()->toDateString(),
                        'created_by'  => $request->user()->id,
                    ]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Status diperbarui.', 'data' => $order->fresh()]);
    }

    /** Admin/Owner: centang tahapan produksi dan penanda baju/DTF. Status pelanggan mengikuti. */
    public function updateProduction(Request $request, Order $order)
    {
        $data = $request->validate([
            'stages'            => 'sometimes|array',
            'stages.*.stage'    => 'required|in:'.implode(',', self::STAGES),
            'stages.*.is_done'  => 'required|boolean',
            'shirt_arrived'     => 'sometimes|boolean',
            'dtf_arrived'       => 'sometimes|boolean',
            'ready_to_press'    => 'sometimes|boolean',
        ]);

        if (in_array($order->status, ['SELESAI', 'DIBATALKAN'], true)) {
            return response()->json(['success' => false, 'message' => 'Pesanan sudah final.'], 422);
        }

        DB::transaction(function () use ($order, $data, $request) {
            foreach ($data['stages'] ?? [] as $s) {
                $order->stages()->where('stage', $s['stage'])->update([
                    'is_done'    => $s['is_done'],
                    'done_at'    => $s['is_done'] ? now() : null,
                    'updated_by' => $request->user()->id,
                ]);
            }

            $order->fill(collect($data)->only(['shirt_arrived', 'dtf_arrived', 'ready_to_press'])->all());
            $order->production_updated_at = now();
            $order->production_updated_by = $request->user()->id;

            // Pemetaan tahapan internal -> status pelanggan (docs/spesifikasi.md).
            // Status SIAP_DIAMBIL tidak diturunkan otomatis.
            if (in_array($order->status, ['DITERIMA', 'SEDANG_DIPROSES', 'CETAK_DTF', 'PRESSING'], true)) {
                $order->status = $this->deriveStatus($order);
            }
            $order->save();

            $this->log($order, $request->user()->name, 'Tahapan produksi diperbarui');
        });

        return response()->json(['success' => true, 'message' => 'Tahapan disimpan.', 'data' => $order->fresh()->load('stages')]);
    }

    private function deriveStatus(Order $order): string
    {
        $done = $order->stages()->where('is_done', 1)->pluck('stage')->all();

        if (in_array('PENYABLONAN', $done, true) || in_array('PEMERIKSAAN_QC', $done, true)) return 'PRESSING';
        if (in_array('CETAK_DTF_MATERIAL', $done, true)) return 'CETAK_DTF';
        if (in_array('DESAIN_MOCKUP', $done, true) || in_array('PENGADAAN_BAJU', $done, true)) return 'SEDANG_DIPROSES';

        return 'DITERIMA';
    }

    private function log(Order $order, string $actor, string $action): void
    {
        ActivityLog::create([
            'order_id'   => $order->id,
            'user_id'    => request()->user()?->id,
            'actor_name' => $actor,
            'action'     => $action,
        ]);
    }
}

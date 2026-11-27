<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /** Publik: katalog untuk tamu dan pelanggan (hanya produk aktif). */
    public function index(Request $request)
    {
        $q = Product::with(['materials', 'colors', 'sizes'])->where('is_active', true);

        if ($request->filled('category')) {
            $q->where('category', $request->query('category'));
        }
        if ($request->filled('search')) {
            $q->where('name', 'like', '%'.$request->query('search').'%');
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('name')->get()]);
    }

    public function show(Product $product)
    {
        return response()->json(['success' => true, 'data' => $product->load(['materials', 'colors', 'sizes'])]);
    }

    /** Owner: semua produk termasuk yang nonaktif. */
    public function ownerIndex()
    {
        return response()->json([
            'success' => true,
            'data'    => Product::with(['materials', 'colors', 'sizes'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $product = DB::transaction(function () use ($data) {
            $product = Product::create($this->productFields($data));
            $this->syncChildren($product, $data);

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk dibuat.',
            'data'    => $product->load(['materials', 'colors', 'sizes']),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate($this->rules(true));

        DB::transaction(function () use ($product, $data) {
            $product->update($this->productFields($data));
            $this->syncChildren($product, $data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Produk diperbarui.',
            'data'    => $product->fresh()->load(['materials', 'colors', 'sizes']),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Produk dihapus.']);
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes|required' : 'required';

        return [
            'name'                  => $req.'|string|max:120',
            'category'              => $req.'|in:kaos_custom,kaos_oversize,baju_polo',
            'material'              => 'nullable|string|max:150',
            'description'           => 'nullable|string',
            'image_path'            => 'nullable|string|max:255',
            'base_price'            => $req.'|integer|min:0',
            'is_active'             => 'sometimes|boolean',
            'materials'             => 'sometimes|array',
            'materials.*.name'      => 'required|string|max:80',
            'materials.*.extra_price' => 'nullable|integer|min:0',
            'materials.*.is_default'  => 'nullable|boolean',
            'colors'                => 'sometimes|array',
            'colors.*.name'         => 'required|string|max:50',
            'colors.*.hex'          => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sizes'                 => 'sometimes|array',
            'sizes.*.label'         => 'required|string|max:10',
            'sizes.*.extra_price'   => 'nullable|integer|min:0',
        ];
    }

    private function productFields(array $data): array
    {
        return collect($data)->only([
            'name', 'category', 'material', 'description', 'image_path', 'base_price', 'is_active',
        ])->all();
    }

    /** Anak (bahan, warna, ukuran) hanya diganti bila dikirim. */
    private function syncChildren(Product $product, array $data): void
    {
        if (isset($data['materials'])) {
            $product->materials()->delete();
            foreach ($data['materials'] as $m) {
                $product->materials()->create([
                    'name'        => $m['name'],
                    'extra_price' => $m['extra_price'] ?? 0,
                    'is_default'  => $m['is_default'] ?? false,
                ]);
            }
        }
        if (isset($data['colors'])) {
            $product->colors()->delete();
            foreach ($data['colors'] as $c) {
                $product->colors()->create(['name' => $c['name'], 'hex' => $c['hex']]);
            }
        }
        if (isset($data['sizes'])) {
            $product->sizes()->delete();
            foreach ($data['sizes'] as $s) {
                $product->sizes()->create(['label' => $s['label'], 'extra_price' => $s['extra_price'] ?? 0]);
            }
        }
    }
}

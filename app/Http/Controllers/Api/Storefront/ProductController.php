<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) config('storefront.branch_id');
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $query = Product::query()
            ->where('is_service', false)
            ->with([
                'category:id,name',
                'brand:id,name',
                'variants.colorOption:id,name,type',
                'variants.sizeOption:id,name,type',
                'variants.branchStocks',
            ])
            ->orderBy('name');

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (Product $product) => $this->serializeProduct($product));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'branch_id' => $branchId,
                'stock_scope' => 'all_branches',
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::query()
            ->where('is_service', false)
            ->with([
                'category:id,name',
                'brand:id,name',
                'variants.colorOption:id,name,type',
                'variants.sizeOption:id,name,type',
                'variants.branchStocks',
            ])
            ->find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(['data' => $this->serializeProduct($product)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeProduct(Product $product): array
    {
        $variants = $product->variants->map(function (ProductVariant $variant) {
            $qty = (int) $variant->branchStocks->sum('quantity');

            return [
                'variant_id' => $variant->id,
                'color_option_id' => $variant->color_option_id,
                'color_name' => $variant->colorOption?->name,
                'size_option_id' => $variant->size_option_id,
                'size_name' => $variant->sizeOption?->name,
                'quantity' => $qty,
            ];
        })->values();

        $stockTotal = (int) $variants->sum('quantity');

        $colors = [];
        $sizes = [];
        foreach ($variants as $v) {
            if (! empty($v['color_option_id'])) {
                $cid = (int) $v['color_option_id'];
                if (! isset($colors[$cid])) {
                    $colors[$cid] = [
                        'id' => $cid,
                        'name' => $v['color_name'],
                        'quantity' => 0,
                    ];
                }
                $colors[$cid]['quantity'] += (int) $v['quantity'];
            }
            if (! empty($v['size_option_id'])) {
                $sid = (int) $v['size_option_id'];
                if (! isset($sizes[$sid])) {
                    $sizes[$sid] = [
                        'id' => $sid,
                        'name' => $v['size_name'],
                    ];
                }
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'original_price' => isset($product->original_price) ? (float) $product->original_price : null,
            'image' => $this->imageUrl($product->image),
            'image_path' => $product->image,
            'category' => $product->category
                ? ['id' => $product->category->id, 'name' => $product->category->name]
                : null,
            'stock' => $stockTotal,
            'is_active' => true,
            'colors' => array_values($colors),
            'sizes' => array_values($sizes),
            'variants' => $variants,
            'brand' => $product->brand?->name,
            'measurements' => [
                'lens_width_mm' => $product->lens_width_mm,
                'bridge_width_mm' => $product->bridge_width_mm,
                'temple_length_mm' => $product->temple_length_mm,
            ],
        ];
    }

    protected function imageUrl(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return Storage::disk('public')->url($image);
    }
}

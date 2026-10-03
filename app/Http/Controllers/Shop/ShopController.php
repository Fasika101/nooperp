<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopCart;
use App\Services\Shop\ShopCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(
        protected ShopCatalogService $catalog,
    ) {}

    public function gender(): View
    {
        ShopCart::resetForNewVisit();

        return view('shop.gender', [
            'genders' => $this->catalog->genders(),
            'brand' => config('storefront.brand_name'),
            'cartCount' => 0,
            'shopStep' => 1,
        ]);
    }

    public function selectGender(int $genderId): RedirectResponse
    {
        $gender = $this->catalog->gender($genderId);
        if (! $gender) {
            return redirect()->route('shop.gender')->with('error', 'Please choose a valid option.');
        }

        ShopCart::setGender($genderId);

        return redirect()->route('shop.frames');
    }

    public function frames(Request $request): View|RedirectResponse
    {
        $cart = ShopCart::get();
        if (empty($cart['gender_id'])) {
            return redirect()->route('shop.gender');
        }

        $gender = $this->catalog->gender((int) $cart['gender_id']);
        if (! $gender) {
            ShopCart::clear();

            return redirect()->route('shop.gender');
        }

        $perPage = 10;
        $page = max(1, (int) $request->query('page', 1));
        $allFrames = $this->catalog->framesForGender((int) $cart['gender_id']);
        $total = $allFrames->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $frames = $allFrames->slice(($page - 1) * $perPage, $perPage)->values();
        $merchantId = (string) config('storefront.banuba.tint_merchant_id', '');

        return view('shop.frames', [
            'gender' => $gender,
            'frames' => $frames,
            'page' => $page,
            'lastPage' => $lastPage,
            'totalFrames' => $total,
            'perPage' => $perPage,
            'brand' => config('storefront.brand_name'),
            'currency' => config('storefront.currency', 'ETB'),
            'cartCount' => ShopCart::totals()['item_count'],
            'banubaReady' => $merchantId !== '',
            'banubaMerchantId' => $merchantId,
            'banubaWidgetUrl' => (string) config('storefront.banuba.widget_url'),
            'stickyNav' => true,
            'backUrl' => route('shop.gender'),
            'backLabel' => 'Back',
            'shopStep' => 2,
            'hasDock' => ShopCart::totals()['item_count'] > 0,
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $cart = ShopCart::get();
        if (empty($cart['gender_id'])) {
            return redirect()->route('shop.gender');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'color_option_id' => ['nullable', 'integer'],
            'size_option_id' => ['nullable', 'integer'],
        ]);

        $frame = $this->catalog->frame((int) $data['product_id']);
        if (! $frame || (int) $frame['gender_option_id'] !== (int) $cart['gender_id']) {
            return back()->with('error', 'Frame not available.');
        }

        $colorId = isset($data['color_option_id']) ? (int) $data['color_option_id'] : null;
        if ($colorId !== null && $colorId <= 0) {
            $colorId = null;
        }
        $sizeId = isset($data['size_option_id']) ? (int) $data['size_option_id'] : null;
        if ($sizeId !== null && $sizeId <= 0) {
            $sizeId = null;
        }

        // Require size when the selected color has size options
        $colorRow = null;
        foreach ($frame['colors'] as $c) {
            $cid = $c['id'] !== null ? (int) $c['id'] : null;
            if ($cid === $colorId || ($colorId === null && $cid === null)) {
                $colorRow = $c;
                break;
            }
        }
        if (! $colorRow && ! empty($frame['colors'])) {
            $colorRow = $frame['colors'][0];
            $colorId = $colorRow['id'] !== null ? (int) $colorRow['id'] : null;
        }

        $sizes = $colorRow['sizes'] ?? [];
        $needsSize = collect($sizes)->contains(fn ($s) => ! empty($s['id']));
        if ($needsSize && ! $sizeId) {
            return back()->with('error', 'Please choose a size.');
        }

        $resolved = $this->catalog->resolveInStockVariant((int) $frame['id'], $colorId, $sizeId);
        if (! $resolved) {
            return back()->with('error', 'That color/size is out of stock.');
        }

        $variant = $resolved['variant'];
        $variant->loadMissing(['colorOption', 'sizeOption']);
        $colorName = $variant->colorOption?->name;
        $sizeName = $variant->sizeOption?->name;
        $label = $frame['name'];
        if ($colorName) {
            $label .= ' — '.$colorName;
        }
        if ($sizeName) {
            $label .= ' / '.$sizeName;
        }

        ShopCart::addItem([
            'product_id' => $frame['id'],
            'variant_id' => $variant->id,
            'name' => $label,
            'price' => $frame['price'],
            'quantity' => 1,
            'color_option_id' => $variant->color_option_id,
            'size_option_id' => $variant->size_option_id,
            'image' => $frame['image'],
        ]);

        return redirect()->route('shop.cart')->with('success', 'Added to cart');
    }
}

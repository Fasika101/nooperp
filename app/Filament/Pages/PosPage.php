<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Customer;
use App\Models\OpticalLensNoPrescription;
use App\Models\OpticalLensPrescriptionRemark;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Setting;
use App\Models\TaxType;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PosPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.pos-page';

    protected static ?string $title = 'Point of Sale';

    protected static ?string $navigationLabel = 'POS';

    protected Width|string|null $maxContentWidth = Width::Full;

    public array $cart = [];

    public ?int $branchId = null;

    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $search = '';

    public ?int $categoryId = null;

    public array $posData = [
        'discountAmount' => 0,
        'discountType' => 'fixed',
        'shippingAmount' => 0,
        'taxTypeId' => null,
        'paymentTypeId' => null,
    ];

    public bool $showAddCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerAddress = '';

    public string $newCustomerTin = '';

    /** products | customize */
    public string $posAreaTab = 'products';

    /** customize: null | no_rx | with_rx */
    public ?string $opticalFlow = null;

    /** single | progressive when opticalFlow is with_rx */
    public ?string $opticalVision = null;

    public string $od_sph = '-';

    public string $od_cyl = '-';

    public string $od_axis = '-';

    public string $od_add = '-';

    public string $os_sph = '-';

    public string $os_cyl = '-';

    public string $os_axis = '-';

    public string $os_add = '-';

    /** null | one | two */
    public ?string $pd_mode = null;

    public string $pd_single = '';

    public string $pd_right = '';

    public string $pd_left = '';

    public ?int $opticalRemarkId = null;

    /** @var int|null Opened when a product has more than one size or color */
    public ?int $variantModalProductId = null;

    public ?int $variantPickColorId = null;

    public ?int $variantPickSizeId = null;

    /** Frame variant on Lens customization tab (syncs to last frame cart line) */
    public ?int $lensFrameSizeOptionId = null;

    public ?int $lensFrameColorOptionId = null;

    public function mount(): void
    {
        $this->branchId = $this->getResolvedBranchId();
    }

    public function getProducts()
    {
        $with = ['category', 'attachedProductOptions'];

        if ($this->branchId) {
            $branchId = $this->branchId;
            $with['branchStocks'] = fn ($q) => $q->where('branch_id', $branchId);
        }

        $query = Product::with($with)
            ->where('is_service', false)
            ->when($this->branchId, function ($query, $branchId) {
                $query->whereHas('branchStocks', fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->where('quantity', '>', 0));
            }, fn ($query) => $query->whereRaw('1 = 0'));

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            });
        }

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        return $query->orderBy('name')->get();
    }

    public function getCategories()
    {
        return Category::orderBy('name')->get();
    }

    public function getBranches()
    {
        if ($this->isBranchLocked()) {
            return Branch::query()
                ->whereKey($this->getResolvedBranchId())
                ->get();
        }

        return Branch::active()->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function addToCart(int $productId): void
    {
        $product = Product::query()->with('attachedProductOptions')->find($productId);
        $availableStock = $this->getAvailableStockForProduct($productId);

        if (! $product || $product->is_service || $availableStock <= 0) {
            Notification::make()
                ->danger()
                ->title('Product out of stock')
                ->send();

            return;
        }

        if ($product->posNeedsVariantModal()) {
            $this->variantModalProductId = $product->id;
            $colors = $product->availableColorOptions();
            $sizes = $product->availableSizeOptions();
            $this->variantPickColorId = $colors->count() === 1 ? (int) $colors->first()->id : null;
            $this->variantPickSizeId = $sizes->count() === 1 ? (int) $sizes->first()->id : null;

            return;
        }

        $colorId = null;
        $sizeId = null;
        $colors = $product->availableColorOptions();
        $sizes = $product->availableSizeOptions();
        if ($colors->count() === 1) {
            $colorId = (int) $colors->first()->id;
        }
        if ($sizes->count() === 1) {
            $sizeId = (int) $sizes->first()->id;
        }

        $this->pushProductLineToCart($product, $colorId, $sizeId);

        Notification::make()
            ->success()
            ->title('Added to cart')
            ->send();
    }

    public function confirmVariantAddToCart(): void
    {
        $product = $this->variantModalProductId
            ? Product::query()->with('attachedProductOptions')->find($this->variantModalProductId)
            : null;

        if (! $product) {
            $this->cancelVariantModal();

            return;
        }

        $availableStock = $this->getAvailableStockForProduct($product->id);
        if ($availableStock <= 0) {
            Notification::make()->danger()->title('Product out of stock')->send();
            $this->cancelVariantModal();

            return;
        }

        $colors = $product->availableColorOptions();
        $sizes = $product->availableSizeOptions();

        $colorId = $this->variantPickColorId;
        $sizeId = $this->variantPickSizeId;

        if ($colors->count() > 1 && ! $colorId) {
            Notification::make()->warning()->title('Select a color')->send();

            return;
        }
        if ($sizes->count() > 1 && ! $sizeId) {
            Notification::make()->warning()->title('Select a size')->send();

            return;
        }
        if ($colors->count() === 1) {
            $colorId = (int) $colors->first()->id;
        }
        if ($sizes->count() === 1) {
            $sizeId = (int) $sizes->first()->id;
        }

        $this->pushProductLineToCart($product, $colorId, $sizeId);
        $this->cancelVariantModal();

        Notification::make()
            ->success()
            ->title('Added to cart')
            ->send();
    }

    public function cancelVariantModal(): void
    {
        $this->variantModalProductId = null;
        $this->variantPickColorId = null;
        $this->variantPickSizeId = null;
    }

    public function getVariantModalProduct(): ?Product
    {
        if (! $this->variantModalProductId) {
            return null;
        }

        return Product::query()->with('attachedProductOptions')->find($this->variantModalProductId);
    }

    /**
     * @return array{index: int, item: array}|null
     */
    public function getLastNonOpticalCartContext(): ?array
    {
        foreach (array_reverse(array_keys($this->cart), true) as $index) {
            $item = $this->cart[$index];
            if (empty($item['is_optical'])) {
                return ['index' => (int) $index, 'item' => $item];
            }
        }

        return null;
    }

    public function hydrateLensFrameVariantFromCart(): void
    {
        $ctx = $this->getLastNonOpticalCartContext();
        if (! $ctx) {
            $this->lensFrameSizeOptionId = null;
            $this->lensFrameColorOptionId = null;

            return;
        }

        $item = $ctx['item'];
        $this->lensFrameSizeOptionId = isset($item['size_option_id']) ? (int) $item['size_option_id'] : null;
        $this->lensFrameColorOptionId = isset($item['color_option_id']) ? (int) $item['color_option_id'] : null;
    }

    public function updatedLensFrameSizeOptionId(mixed $value): void
    {
        $this->lensFrameSizeOptionId = ($value === '' || $value === null) ? null : (int) $value;
        $this->applyLensFrameSelectionToCartLine();
    }

    public function updatedLensFrameColorOptionId(mixed $value): void
    {
        $this->lensFrameColorOptionId = ($value === '' || $value === null) ? null : (int) $value;
        $this->applyLensFrameSelectionToCartLine();
    }

    protected function applyLensFrameSelectionToCartLine(): void
    {
        $ctx = $this->getLastNonOpticalCartContext();
        if (! $ctx) {
            return;
        }

        $index = $ctx['index'];
        $product = Product::query()->find($this->cart[$index]['product_id'] ?? null);
        if (! $product) {
            return;
        }

        $sid = $this->lensFrameSizeOptionId;
        $cid = $this->lensFrameColorOptionId;

        if ($sid !== null && ! $product->availableSizeOptions()->pluck('id')->contains($sid)) {
            $sid = null;
        }
        if ($cid !== null && ! $product->availableColorOptions()->pluck('id')->contains($cid)) {
            $cid = null;
        }

        $this->cart[$index]['size_option_id'] = $sid;
        $this->cart[$index]['color_option_id'] = $cid;
        $this->cart[$index]['size_name'] = $sid ? (string) ProductOption::query()->whereKey($sid)->value('name') : null;
        $this->cart[$index]['color_name'] = $cid ? (string) ProductOption::query()->whereKey($cid)->value('name') : null;
        $this->cart[$index]['name'] = $product->formatNameWithVariant($sid, $cid);
    }

    protected function pushProductLineToCart(Product $product, ?int $colorOptionId, ?int $sizeOptionId): void
    {
        $availableStock = $this->getAvailableStockForProduct($product->id);
        if ($availableStock <= 0) {
            Notification::make()
                ->danger()
                ->title('Product out of stock')
                ->send();

            return;
        }

        $idx = $this->findCartLineIndexForProductVariant($product->id, $colorOptionId, $sizeOptionId);
        if ($idx !== false) {
            $currentQty = $this->cart[$idx]['quantity'];
            if ($currentQty >= $availableStock) {
                Notification::make()
                    ->warning()
                    ->title('Maximum stock reached')
                    ->send();

                return;
            }
            $this->cart[$idx]['quantity']++;
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->formatNameWithVariant($sizeOptionId, $colorOptionId),
                'line_label' => null,
                'color_option_id' => $colorOptionId,
                'size_option_id' => $sizeOptionId,
                'color_name' => $colorOptionId ? (string) ProductOption::query()->whereKey($colorOptionId)->value('name') : null,
                'size_name' => $sizeOptionId ? (string) ProductOption::query()->whereKey($sizeOptionId)->value('name') : null,
                'price' => (float) $product->price,
                'unit_cost' => (float) ($product->cost_price ?? $product->original_price ?? 0),
                'quantity' => 1,
                'image' => $product->image,
                'is_optical' => false,
                'optical_meta' => null,
            ];
        }

        if ($this->posAreaTab === 'customize') {
            $this->hydrateLensFrameVariantFromCart();
        }
    }

    protected function findCartLineIndexForProductVariant(int $productId, ?int $colorOptionId, ?int $sizeOptionId): int|false
    {
        foreach ($this->cart as $index => $item) {
            if (! empty($item['is_optical'])) {
                continue;
            }
            if ((int) ($item['product_id'] ?? 0) !== $productId) {
                continue;
            }
            if (($item['color_option_id'] ?? null) !== $colorOptionId) {
                continue;
            }
            if (($item['size_option_id'] ?? null) !== $sizeOptionId) {
                continue;
            }

            return (int) $index;
        }

        return false;
    }

    protected function frameMetaForOpticalLine(): array
    {
        return [
            'size_option_id' => $this->lensFrameSizeOptionId,
            'size_name' => $this->lensFrameSizeOptionId
                ? (string) ProductOption::query()->whereKey($this->lensFrameSizeOptionId)->value('name')
                : null,
            'color_option_id' => $this->lensFrameColorOptionId,
            'color_name' => $this->lensFrameColorOptionId
                ? (string) ProductOption::query()->whereKey($this->lensFrameColorOptionId)->value('name')
                : null,
        ];
    }

    public function removeFromCart(int $index): void
    {
        array_splice($this->cart, $index, 1);
        if ($this->posAreaTab === 'customize') {
            $this->hydrateLensFrameVariantFromCart();
        }
    }

    public function updateCartQuantity(int $index, int $quantity): void
    {
        if (! empty($this->cart[$index]['is_optical'])) {
            return;
        }

        if ($quantity < 1) {
            $this->removeFromCart($index);

            return;
        }

        $availableStock = $this->getAvailableStockForProduct($this->cart[$index]['product_id']);
        if ($availableStock > 0 && $quantity > $availableStock) {
            $quantity = $availableStock;
        }

        $this->cart[$index]['quantity'] = $quantity;
    }

    public function getSubtotal(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['price'] * $item['quantity']);
    }

    public function getCartTotal(): float
    {
        return $this->getSubtotal();
    }

    public function getDiscountValue(): float
    {
        $subtotal = $this->getSubtotal();
        $data = $this->posData;
        $amount = (float) ($data['discountAmount'] ?? 0);
        if (($data['discountType'] ?? 'fixed') === 'percentage') {
            return round($subtotal * ($amount / 100), 2);
        }

        return min($amount, $subtotal);
    }

    public function getDiscountedSubtotal(): float
    {
        return $this->getSubtotal() - $this->getDiscountValue();
    }

    public function getTaxValue(): float
    {
        $taxTypeId = $this->posData['taxTypeId'] ?? null;
        if (! $taxTypeId || $taxTypeId === '') {
            return 0;
        }
        $taxType = TaxType::find((int) $taxTypeId);
        if (! $taxType) {
            return 0;
        }

        return round($this->getDiscountedSubtotal() * ($taxType->rate / 100), 2);
    }

    public function getFinalTotal(): float
    {
        $data = $this->posData;

        return $this->getDiscountedSubtotal() + (float) ($data['shippingAmount'] ?? 0) + $this->getTaxValue();
    }

    public function getCartCount(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    public function getActiveTaxTypes()
    {
        return TaxType::active()->orderBy('name')->get();
    }

    public function getActivePaymentTypes()
    {
        return PaymentType::active()
            ->forBranch($this->branchId)
            ->orderBy('name')
            ->get();
    }

    public function getDefaultCurrency(): string
    {
        return Setting::getDefaultCurrency();
    }

    public function getCustomerSuggestions()
    {
        if ($this->customerSearch === '') {
            return collect();
        }

        return Customer::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.$this->customerSearch.'%')
            ->orderByRaw('phone like ? desc', [$this->customerSearch.'%'])
            ->orderByRaw('phone IS NULL')
            ->orderBy('phone')
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function getSelectedCustomer(): ?Customer
    {
        if (! $this->customerId) {
            return null;
        }

        return Customer::find($this->customerId);
    }

    public function updatedCustomerSearch(): void
    {
        $selectedCustomer = $this->getSelectedCustomer();

        if ($selectedCustomer && $selectedCustomer->phone !== $this->customerSearch) {
            $this->customerId = null;
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return;
        }

        $this->customerId = $customer->id;
        $this->customerSearch = (string) ($customer->phone ?? '');
    }

    public function updatedBranchId(): void
    {
        if ($this->isBranchLocked()) {
            $this->branchId = $this->getResolvedBranchId();

            return;
        }

        $this->posData['paymentTypeId'] = null;

        if ($this->cart !== []) {
            $this->cart = [];

            Notification::make()
                ->warning()
                ->title('Branch changed')
                ->body('Cart was cleared to use stock from the selected branch.')
                ->send();
        }
    }

    public function addNewCustomer(): void
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:255',
            'newCustomerPhone' => 'required|string|max:255',
            'newCustomerAddress' => 'nullable|string',
            'newCustomerTin' => 'nullable|string|max:255',
        ], [
            'newCustomerName.required' => 'Name is required',
            'newCustomerPhone.required' => 'Phone is required',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'email' => null,
            'address' => $this->newCustomerAddress ?: null,
            'tin' => $this->newCustomerTin ?: null,
        ]);

        $this->customerId = $customer->id;
        $this->showAddCustomerForm = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerAddress = '';
        $this->newCustomerTin = '';

        Notification::make()
            ->success()
            ->title('Customer added')
            ->send();
    }

    public function useWalkInCustomer(): void
    {
        $this->customerId = null;
        $this->customerSearch = '';
        $this->showAddCustomerForm = false;
    }

    public function cancelAddCustomer(): void
    {
        $this->showAddCustomerForm = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerAddress = '';
        $this->newCustomerTin = '';
    }

    public function checkout(): void
    {
        if (! $this->branchId) {
            Notification::make()
                ->warning()
                ->title('Select branch')
                ->body('Choose a branch before completing the sale.')
                ->send();

            return;
        }

        if (empty($this->cart)) {
            Notification::make()
                ->warning()
                ->title('Cart is empty')
                ->send();

            return;
        }

        $paymentTypeId = $this->posData['paymentTypeId'] ?? null;
        if (! $paymentTypeId || $paymentTypeId === '') {
            Notification::make()
                ->warning()
                ->title('Select payment type')
                ->body('Please choose a payment type before completing the sale.')
                ->send();

            return;
        }

        $customerId = $this->customerId;
        if (! $customerId) {
            $walkIn = Customer::firstOrCreate(
                ['email' => 'walkin@pos.local'],
                ['name' => 'Walk-in Customer', 'phone' => null, 'address' => null, 'tin' => null]
            );
            $customerId = $walkIn->id;
        }

        try {
            $order = null;
            DB::transaction(function () use ($customerId, $paymentTypeId, &$order) {
                $totalAmount = $this->getFinalTotal();
                $discountValue = $this->getDiscountValue();
                $taxValue = $this->getTaxValue();
                $branchId = $this->branchId;

                $data = $this->posData;
                $order = Order::create([
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discountValue,
                    'discount_type' => $data['discountType'] ?? 'fixed',
                    'shipping_amount' => (float) ($data['shippingAmount'] ?? 0),
                    'tax_amount' => $taxValue,
                    'tax_type_id' => filled($data['taxTypeId'] ?? null) ? (int) $data['taxTypeId'] : null,
                    'status' => 'completed',
                ]);

                foreach ($this->cart as $item) {
                    $product = Product::query()->whereKey($item['product_id'])->first();

                    if ($product && ! $product->is_service) {
                        $branchStock = BranchProductStock::query()
                            ->where('branch_id', $branchId)
                            ->where('product_id', $item['product_id'])
                            ->lockForUpdate()
                            ->first();

                        if (! $branchStock || $branchStock->quantity < $item['quantity']) {
                            throw new \RuntimeException("Insufficient stock for {$item['name']} in the selected branch.");
                        }

                        $branchStock->decrement('quantity', $item['quantity']);
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'size_option_id' => $item['size_option_id'] ?? null,
                        'color_option_id' => $item['color_option_id'] ?? null,
                        'line_label' => $item['line_label'] ?? null,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'optical_meta' => $item['optical_meta'] ?? null,
                    ]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'branch_id' => $branchId,
                    'payment_type_id' => (int) $paymentTypeId,
                    'amount' => $totalAmount,
                    'payment_method' => PaymentType::find($paymentTypeId)?->name ?? 'cash',
                    'status' => 'completed',
                ]);
            });

            $lastOrderId = $order->id ?? null;
            $this->cart = [];
            $this->customerId = null;
            $this->customerSearch = '';
            $this->posData = [
                'discountAmount' => 0,
                'discountType' => 'fixed',
                'shippingAmount' => 0,
                'taxTypeId' => null,
                'paymentTypeId' => null,
            ];

            Notification::make()
                ->success()
                ->title('Sale completed successfully')
                ->actions([
                    Action::make('viewReceipt')
                        ->label('Print Receipt')
                        ->url(route('receipt.show', $lastOrderId), shouldOpenInNewTab: true),
                    Action::make('downloadPdf')
                        ->label('Download PDF')
                        ->url(route('receipt.pdf', $lastOrderId), shouldOpenInNewTab: true),
                ])
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error processing sale')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->customerSearch = '';
        $this->cancelVariantModal();
        $this->lensFrameSizeOptionId = null;
        $this->lensFrameColorOptionId = null;
        $this->posData = [
            'discountAmount' => 0,
            'discountType' => 'fixed',
            'shippingAmount' => 0,
            'taxTypeId' => null,
            'paymentTypeId' => null,
        ];
    }

    public function updatedPosAreaTab(string $value): void
    {
        if ($value === 'products') {
            $this->resetOpticalWizardState();
        } else {
            $this->hydrateLensFrameVariantFromCart();
        }
    }

    public function resetOpticalWizardState(): void
    {
        $this->opticalFlow = null;
        $this->opticalVision = null;
        $this->opticalRemarkId = null;
        $this->pd_mode = null;
        $this->pd_single = '';
        $this->pd_right = '';
        $this->pd_left = '';
        $this->od_sph = '-';
        $this->od_cyl = '-';
        $this->od_axis = '-';
        $this->od_add = '-';
        $this->os_sph = '-';
        $this->os_cyl = '-';
        $this->os_axis = '-';
        $this->os_add = '-';
    }

    public function selectOpticalFlowNoRx(): void
    {
        $this->opticalFlow = 'no_rx';
        $this->opticalVision = null;
        $this->opticalRemarkId = null;
    }

    public function selectOpticalFlowWithRx(): void
    {
        $this->opticalFlow = 'with_rx';
        $this->opticalVision = null;
        $this->opticalRemarkId = null;
        $this->resetPrescriptionFields();
    }

    public function cancelOpticalFlow(): void
    {
        $this->resetOpticalWizardState();
    }

    public function cancelOpticalVisionSelection(): void
    {
        $this->opticalVision = null;
        $this->opticalRemarkId = null;
        $this->resetPrescriptionFields();
    }

    public function setOpticalVision(string $vision): void
    {
        if (! in_array($vision, ['single', 'progressive'], true)) {
            return;
        }
        $this->opticalVision = $vision;
        $this->opticalRemarkId = null;
        $this->resetPrescriptionFields();
    }

    protected function resetPrescriptionFields(): void
    {
        $this->od_sph = '-';
        $this->od_cyl = '-';
        $this->od_axis = '-';
        $this->od_add = '-';
        $this->os_sph = '-';
        $this->os_cyl = '-';
        $this->os_axis = '-';
        $this->os_add = '-';
        $this->pd_mode = null;
        $this->pd_single = '';
        $this->pd_right = '';
        $this->pd_left = '';
    }

    public function getOpticalNoPrescriptionLenses()
    {
        return OpticalLensNoPrescription::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getOpticalPrescriptionRemarks()
    {
        return OpticalLensPrescriptionRemark::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getOpticalRxSphereOptions(): array
    {
        // Single vision: −6.00 to +6.00; progressive: −3.00 to +3.00 (quarter steps).
        if ($this->opticalVision === 'progressive') {
            return $this->buildQuarterDiopterOptions(-3.0, 3.0);
        }

        return $this->buildQuarterDiopterOptions(-6.0, 6.0);
    }

    public function getOpticalRxCylinderOptions(): array
    {
        // Single & progressive: −3.00 to +3.00.
        return $this->buildQuarterDiopterOptions(-3.0, 3.0);
    }

    public function getOpticalRxAddOptions(): array
    {
        $opts = ['-' => '—'];
        for ($i = 1; $i <= 12; $i++) {
            $v = round($i * 0.25, 2);
            $k = number_format($v, 2, '.', '');
            $opts[$k] = '+'.$k;
        }

        return $opts;
    }

    public function getOpticalRxAxisOptions(): array
    {
        $opts = ['-' => '—'];
        for ($i = 1; $i <= 180; $i++) {
            $opts[(string) $i] = (string) $i;
        }

        return $opts;
    }

    /**
     * @return array<string, string>
     */
    protected function buildQuarterDiopterOptions(float $min, float $max): array
    {
        $opts = ['-' => '—'];
        $steps = (int) round(($max - $min) / 0.25);
        for ($n = 0; $n <= $steps; $n++) {
            $v = round($min + ($n * 0.25), 2);
            $k = number_format($v, 2, '.', '');
            $opts[$k] = $k;
        }

        return $opts;
    }

    public function appendOpticalCartLine(string $lineLabel, float $price, array $opticalMeta): void
    {
        $serviceId = Product::opticalServiceProductId();
        if (! $serviceId) {
            Notification::make()
                ->danger()
                ->title('Optical service product missing')
                ->body('Run migrations or create a product flagged as non-inventory (service) for POS lens lines.')
                ->send();

            return;
        }

        $this->cart[] = [
            'product_id' => $serviceId,
            'name' => $lineLabel,
            'line_label' => $lineLabel,
            'color_option_id' => null,
            'size_option_id' => null,
            'color_name' => null,
            'size_name' => null,
            'price' => $price,
            'unit_cost' => 0.0,
            'quantity' => 1,
            'image' => null,
            'is_optical' => true,
            'optical_meta' => $opticalMeta,
        ];
    }

    public function addOpticalNoPrescriptionLens(int $lensId): void
    {
        $lens = OpticalLensNoPrescription::query()->where('is_active', true)->whereKey($lensId)->first();
        if (! $lens) {
            Notification::make()->danger()->title('Lens option not found')->send();

            return;
        }

        $lineLabel = 'Lens (no prescription): '.$lens->name;
        $this->appendOpticalCartLine($lineLabel, (float) $lens->price, [
            'route' => 'no_prescription',
            'vision' => null,
            'lens_name' => $lens->name,
            'lens_id' => $lens->id,
            'frame' => $this->frameMetaForOpticalLine(),
        ]);

        Notification::make()
            ->success()
            ->title('Lens added to cart')
            ->send();
    }

    public function addOpticalPrescriptionLensToCart(): void
    {
        if ($this->opticalFlow !== 'with_rx' || ! $this->opticalVision) {
            Notification::make()
                ->warning()
                ->title('Select vision type')
                ->send();

            return;
        }

        $remark = $this->opticalRemarkId
            ? OpticalLensPrescriptionRemark::query()->where('is_active', true)->whereKey($this->opticalRemarkId)->first()
            : null;

        if (! $remark) {
            Notification::make()
                ->warning()
                ->title('Select a lens type')
                ->body('Choose one option under Lens type remarks.')
                ->send();

            return;
        }

        $price = $remark->priceForVision($this->opticalVision);
        $visionLabel = $this->opticalVision === 'progressive' ? 'Progressive' : 'Single vision';

        $opticalMeta = [
            'route' => 'prescription',
            'vision' => $this->opticalVision,
            'lens_name' => $remark->name,
            'remark_id' => $remark->id,
            'od' => [
                'sph' => $this->od_sph,
                'cyl' => $this->od_cyl,
                'axis' => $this->od_axis,
                'add' => $this->opticalVision === 'progressive' ? $this->od_add : null,
            ],
            'os' => [
                'sph' => $this->os_sph,
                'cyl' => $this->os_cyl,
                'axis' => $this->os_axis,
                'add' => $this->opticalVision === 'progressive' ? $this->os_add : null,
            ],
            'pd' => [
                'mode' => $this->pd_mode,
                'single' => $this->pd_single !== '' ? $this->pd_single : null,
                'right' => $this->pd_right !== '' ? $this->pd_right : null,
                'left' => $this->pd_left !== '' ? $this->pd_left : null,
            ],
            'frame' => $this->frameMetaForOpticalLine(),
        ];

        $lineLabel = 'Lens (Rx, '.$visionLabel.'): '.$remark->name;

        $this->appendOpticalCartLine($lineLabel, $price, $opticalMeta);

        Notification::make()
            ->success()
            ->title('Prescription lens added to cart')
            ->send();
    }

    public function setPdMode(?string $mode): void
    {
        if ($mode !== null && ! in_array($mode, ['one', 'two'], true)) {
            return;
        }
        $this->pd_mode = $mode;
        if ($mode !== 'one') {
            $this->pd_single = '';
        }
        if ($mode !== 'two') {
            $this->pd_right = '';
            $this->pd_left = '';
        }
    }

    protected function getProductImageUrl(?string $image): string
    {
        if ($image) {
            return Storage::disk('public')->url($image);
        }

        return 'https://ui-avatars.com/api/?name=Product&color=7F9CF5&background=EBF4FF';
    }

    public function getOpticalLineImageUrl(): string
    {
        return 'https://ui-avatars.com/api/?name=Lens&color=0F766E&background=CCFBF1';
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return ['fi-page-pos'];
    }

    protected function getAvailableStockForProduct(int $productId): int
    {
        if (! $this->branchId) {
            return 0;
        }

        return (int) BranchProductStock::query()
            ->where('branch_id', $this->branchId)
            ->where('product_id', $productId)
            ->value('quantity');
    }

    public function isBranchLocked(): bool
    {
        return Auth::user()?->isBranchRestricted() ?? false;
    }

    protected function getResolvedBranchId(): ?int
    {
        $user = Auth::user();

        if ($user?->isBranchRestricted()) {
            return $user->branch_id;
        }

        return Branch::getDefaultBranch()?->id;
    }
}

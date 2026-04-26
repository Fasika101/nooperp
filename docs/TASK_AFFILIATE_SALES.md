# Task: Affiliate sales

## Completed work

1. **Database**
   - Added `affiliates` table: name, phone, email, code (optional unique), default commission type/rate, `is_active`, notes.
   - Added to `orders`: `affiliate_id` (nullable FK, `nullOnDelete`), `affiliate_commission_type`, `affiliate_commission_rate`, `affiliate_commission_amount` (migrations: `2026_04_25_000001_*`, `2026_04_25_000002_*`).

2. **Models**
   - `App\Models\Affiliate` with `COMMISSION_ADD_PERCENT` / `COMMISSION_DEDUCT_PERCENT` and `orders()` relation.
   - `App\Models\Order`: fillable + casts for affiliate fields; `affiliate()` `BelongsTo` relation.

3. **Filament — Affiliates**
   - `AffiliateResource` under **Sales** nav: list (with **Total earned** via `withSum` on completed orders), create/edit, view.
   - View page: profile, computed **Total earned** and **Sales count** from completed orders, infolist.
   - `AffiliateOrdersRelationManager`: earning history (completed orders, commission columns, link on order #).

4. **Filament — Orders**
   - `OrderResource` table: optional **Affiliate** and **Aff. cut** columns.
   - Form: **Affiliate** section (read-only) when an order has an affiliate.

5. **POS**
   - `PosPage`: **Set affiliate** / summary pill with Change & Remove; **modal** to search (name, phone, code), pick from list, set **Deduct %** vs **Add %** and **rate**; **Apply** to lock for this sale; optional **Add new affiliate** (checkbox + form → **Create & use**).
   - `getPosOrderBaseTotal()` = subtotal + shipping + tax (after discount); `getAffiliateCommissionAmount()` = base × rate%; **Add %** adds that amount to the **Total**; **Deduct %** leaves customer total = base and still stores the commission on the order.
   - On checkout, affiliate fields are saved on the `Order` when affiliate was applied; state resets after a successful sale and on **Clear cart**.

6. **Bugfix during continuation**
   - `AffiliateOrdersRelationManager`: removed an erroneous comma that broke the `TextColumn` method chain and caused a `ParseError`.

7. **Tooling**
   - Ran `php artisan migrate` and `vendor/bin/pint --dirty` (import/order fixes in `PosPage.php`).

8. **Affiliate payouts (expenses)**
   - Migration `2026_04_25_100000_add_affiliate_payouts_to_expenses.php`: `expenses.affiliate_id` (nullable FK to `affiliates`); seeds expense type **Affiliate commission payout** (`ExpenseType::NAME_AFFILIATE_PAYOUT` + `affiliatePayoutTypeId()`).
   - `Expense`: `affiliate()` relation; `saving` clears `affiliate_id` unless the type is the affiliate-payout type.
   - `ExpenseResource`: when type is **Affiliate commission payout**, **Affiliate** is required; vendor prefilled from affiliate; list/infolist/filter for affiliate.
   - `CreateExpense` / `EditExpense`: query params `affiliate_id` + `expense_type_id`; `mutateFormData*` clears `affiliate_id` when type is not payout (mirrors salary/employee).
   - `AffiliatePayoutsRelationManager`: list payout expenses, **Record payout** → new expense with type + affiliate prefilled; each payout is a normal **Expense** so existing `ExpenseObserver` posts the **bank withdrawal**.
   - Affiliate **view** infolist: **Total paid (payout expenses)** and **Balance (earned minus paid)**.

## Follow-ups (optional)

- Run `php artisan shield:generate` (or your Shield workflow) so roles get permissions for **Affiliates** and staff can open the new resource in production.
- If the POS user role should only use the modal and not the Affiliates CRUD, adjust Shield policies accordingly.

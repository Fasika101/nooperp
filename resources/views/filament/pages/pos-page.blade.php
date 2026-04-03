@push('styles')
<style>
.fi-page-pos .fi-page-header-main-ctn { padding-top: 0; padding-bottom: 0; gap: 0; }
.fi-page-pos .fi-page-main { gap: 0; }
.fi-page-pos .fi-page-content { gap: 0; }

/* POS layout - inline CSS since Filament theme doesn't include our Tailwind classes */
.pos-page { display: flex; flex-direction: column; height: calc(100vh - 4rem); margin: 0 -1rem; }
@media (min-width: 768px) { .pos-page { margin: 0 -1.5rem; } }
@media (min-width: 1024px) { .pos-page { margin: 0 -2rem; } }

.pos-topbar { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; border-bottom: 1px solid rgb(229 231 235); background: white; flex-shrink: 0; }
.dark .pos-topbar { border-color: rgba(255,255,255,0.1); background: rgb(17 24 39); }
.pos-topbar h1 { font-size: 1.125rem; font-weight: 600; color: rgb(3 7 18); flex-shrink: 0; }
.dark .pos-topbar h1 { color: white; }
.pos-search-wrap { flex: 1; max-width: 28rem; position: relative; }
.pos-search-wrap input { width: 100%; padding: 0.5rem 1rem 0.5rem 2.5rem; border-radius: 0.5rem; border: 1px solid rgb(209 213 219); background: white; font-size: 0.875rem; }
.dark .pos-search-wrap input { border-color: rgb(75 85 99); background: rgb(31 41 55); color: white; }
.pos-search-wrap input::placeholder { color: rgb(156 163 175); }
.pos-search-wrap input:focus { outline: none; border-color: var(--primary-500);
box-shadow: 0 0 0 1px var(--primary-500); }
.pos-search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 1.25rem; height: 1.25rem; color: rgb(156 163 175); pointer-events: none; display: block; }
.pos-search-icon svg { width: 100%; height: 100%; }

.pos-main { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
@media (min-width: 1024px) { .pos-main { flex-direction: row; overflow: hidden; } }

.pos-products { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; min-height: 50vh; }
@media (min-width: 1024px) { .pos-products { min-height: 0; } }

.pos-categories { display: flex; gap: 0.5rem; padding: 0.75rem 1rem; overflow-x: auto; flex-shrink: 0; border-bottom: 1px solid rgb(229 231 235); background: rgba(249,250,251,0.5); }
@media (min-width: 768px) { .pos-categories { padding: 0.75rem 1.5rem; } }
@media (min-width: 1024px) { .pos-categories { padding: 0.75rem 2rem; } }
.dark .pos-categories { border-color: rgba(255,255,255,0.1); background: rgba(17,24,39,0.5); }
.pos-cat-btn { padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; white-space: nowrap; border: none; cursor: pointer; transition: all 0.15s; }
.pos-cat-btn.active { background: var(--primary-600); color: white; }
.pos-cat-btn:not(.active) { background: white; color: rgb(55 65 81); }
.dark .pos-cat-btn:not(.active) { background: rgb(31 41 55); color: rgb(209 213 219); }
.pos-cat-btn:not(.active):hover { background: rgb(243 244 246); }
.dark .pos-cat-btn:not(.active):hover { background: rgb(55 65 81); }

.pos-grid-wrap { flex: 1; overflow-y: auto; padding: 1rem; }
@media (min-width: 768px) { .pos-grid-wrap { padding: 1.5rem; } }
.pos-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
@media (min-width: 640px) { .pos-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 768px) { .pos-grid { grid-template-columns: repeat(4, 1fr); gap: 1rem; } }
@media (min-width: 1024px) { .pos-grid { grid-template-columns: repeat(5, 1fr); } }
@media (min-width: 1280px) { .pos-grid { grid-template-columns: repeat(6, 1fr); } }

.pos-product-card { display: flex; flex-direction: column; border-radius: 0.75rem; background: white; border: 1px solid rgb(229 231 235); overflow: hidden; text-align: left; cursor: pointer; transition: all 0.2s; }
.dark .pos-product-card { background: rgb(31 41 55); border-color: rgba(255,255,255,0.1); }
.pos-product-card:hover { border-color: var(--primary-500); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
.pos-product-card:active { transform: scale(0.98); }
.pos-product-img { aspect-ratio: 1; overflow: hidden; background: rgb(243 244 246); }
.dark .pos-product-img { background: rgb(55 65 81); }
.pos-product-img img { width: 100%; height: 100%; object-fit: cover; }
.pos-product-card:hover .pos-product-img img { transform: scale(1.05); }
.pos-product-img img { transition: transform 0.2s; }
.pos-product-info { padding: 0.5rem 0.75rem; }
@media (min-width: 768px) { .pos-product-info { padding: 0.75rem; } }
.pos-product-name { font-weight: 500; font-size: 0.875rem; color: rgb(3 7 18); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.dark .pos-product-name { color: white; }
.pos-product-prices { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.25rem 0.5rem; }
.pos-product-original { font-size: 0.75rem; color: rgb(107 114 128); text-decoration: line-through; }
.dark .pos-product-original { color: rgb(156 163 175); }
.pos-product-price { font-size: 0.875rem; font-weight: 600; color: var(--primary-600); }
.pos-product-stock { font-size: 0.75rem; color: rgb(107 114 128); }
.dark .pos-product-stock { color: rgb(156 163 175); }

.pos-empty { grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem 1rem; color: rgb(107 114 128); }
.dark .pos-empty { color: rgb(156 163 175); }
.pos-empty-icon { width: 4rem; height: 4rem; margin-bottom: 1rem; opacity: 0.5; display: block; }
.pos-empty-icon svg { width: 100%; height: 100%; }
.pos-empty p { margin: 0; }
.pos-empty p:first-of-type { font-size: 1rem; font-weight: 500; }
.pos-empty p:last-of-type { font-size: 0.875rem; margin-top: 0.25rem; }

.pos-cart { width: 100%; display: flex; flex-direction: column; flex-shrink: 0; border-top: 1px solid rgb(229 231 235); background: white; min-height: 0; }
@media (min-width: 1024px) { .pos-cart { width: 24rem; border-top: none; border-left: 1px solid rgb(229 231 235); } }
.dark .pos-cart { border-color: rgba(255,255,255,0.1); background: rgb(17 24 39); }
.pos-cart-header { padding: 1rem; border-bottom: 1px solid rgb(229 231 235); display: flex; align-items: center; justify-content: space-between; }
.dark .pos-cart-header { border-color: rgba(255,255,255,0.1); }
.pos-cart-header h2 { font-weight: 600; font-size: 1rem; color: rgb(3 7 18); }
.dark .pos-cart-header h2 { color: white; }
.pos-cart-header h2 span { font-weight: 400; color: rgb(107 114 128); }
.dark .pos-cart-header h2 span { color: rgb(156 163 175); }
.pos-cart-items { flex: 1; overflow-y: auto; padding: 1rem; min-height: 0; -webkit-overflow-scrolling: touch; }
.pos-cart-item { display: flex; gap: 0.75rem; padding: 0.75rem; border-radius: 0.5rem; background: rgb(249 250 251); margin-bottom: 0.75rem; }
.dark .pos-cart-item { background: rgba(31,41,55,0.5); }
.pos-cart-item:last-child { margin-bottom: 0; }
.pos-cart-item-img { width: 3rem; height: 3rem; border-radius: 0.5rem; overflow: hidden; flex-shrink: 0; background: rgb(229 231 235); }
.dark .pos-cart-item-img { background: rgb(55 65 81); }
.pos-cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
.pos-cart-item-body { flex: 1; min-width: 0; }
.pos-cart-item-name { font-weight: 500; font-size: 0.875rem; color: rgb(3 7 18); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dark .pos-cart-item-name { color: white; }
.pos-cart-item-qty { display: flex; align-items: center; gap: 0.375rem; margin-top: 0.25rem; }
.pos-cart-item-qty button { width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background: rgb(229 231 235); border: none; cursor: pointer; font-weight: 700; font-size: 0.875rem; display: flex; align-items: center; justify-content: center; color: rgb(55 65 81); }
.dark .pos-cart-item-qty button { background: rgb(75 85 99); color: rgb(229 231 235); }
.pos-cart-item-qty button:hover { background: rgb(209 213 219); }
.dark .pos-cart-item-qty button:hover { background: rgb(107 114 128); }
.pos-cart-item-qty span { width: 2rem; text-align: center; font-size: 0.875rem; font-weight: 500; color: rgb(3 7 18); }
.dark .pos-cart-item-qty span { color: white; }
.pos-cart-item-right { display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 0.25rem; }
.pos-cart-item-total { font-weight: 600; font-size: 0.875rem; color: rgb(3 7 18); }
.dark .pos-cart-item-total { color: white; }
.pos-cart-item-remove { padding: 0.25rem; border-radius: 0.25rem; color: rgb(220 38 38); background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.pos-cart-item-remove:hover { color: rgb(185 28 28); }
.pos-cart-item-remove svg { width: 1rem; height: 1rem; }
.dark .pos-cart-item-remove { color: rgb(248 113 113); }
.dark .pos-cart-item-remove:hover { color: rgb(252 165 165); }
.pos-cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1rem; text-align: center; }
.pos-cart-empty-icon { width: 3.5rem; height: 3.5rem; margin-bottom: 0.75rem; color: rgb(156 163 175); display: block; }
.pos-cart-empty-icon svg { width: 100%; height: 100%; }
.pos-cart-empty p { margin: 0; font-size: 0.875rem; font-weight: 500; color: rgb(75 85 99); }
.dark .pos-cart-empty p { color: rgb(156 163 175); }
.pos-cart-empty p:last-of-type { font-size: 0.75rem; margin-top: 0.25rem; color: rgb(107 114 128); }
.dark .pos-cart-empty p:last-of-type { color: rgb(107 114 128); }
.pos-cart-footer { padding: 1rem; border-top: 1px solid rgb(229 231 235); flex-shrink: 0; overflow-y: auto; -webkit-overflow-scrolling: touch; max-height: min(45vh, 380px); }
.dark .pos-cart-footer { border-color: rgba(255,255,255,0.1); }
.pos-cart-checkout { padding: 1rem; border-top: 1px solid rgb(229 231 235); flex-shrink: 0; background: white; }
.pos-cart-checkout .fi-btn { width: 100%; margin-top: 0; }
.dark .pos-cart-checkout { border-color: rgba(255,255,255,0.1); background: rgb(17 24 39); }
.pos-cart-footer label { display: block; font-size: 0.75rem; font-weight: 500; color: rgb(107 114 128); margin-bottom: 0.25rem; }
.dark .pos-cart-footer label { color: rgb(156 163 175); }
.pos-cart-footer select { width: 100%; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid rgb(209 213 219); background: white; font-size: 0.875rem; color: rgb(3 7 18); }
.dark .pos-cart-footer select { border-color: rgb(75 85 99); background: rgb(31 41 55); color: white; }
.pos-cart-footer select:focus { outline: none; border-color: var(--primary-500); }
.pos-cart-total { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; }
.pos-cart-total span:first-child { font-size: 1rem; font-weight: 600; color: rgb(3 7 18); }
.dark .pos-cart-total span:first-child { color: white; }
.pos-cart-total span:last-child { font-size: 1.125rem; font-weight: 700; color: var(--primary-600); }
.pos-cart-footer .fi-btn { width: 100%; margin-top: 1rem; }
.pos-cart-field { margin-bottom: 0.75rem; }
.pos-cart-field label { display: block; font-size: 0.75rem; font-weight: 500; color: rgb(107 114 128); margin-bottom: 0.25rem; }
.dark .pos-cart-field label { color: rgb(156 163 175); }
.pos-cart-field input, .pos-cart-field select { width: 100%; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid rgb(209 213 219); background: white; font-size: 0.875rem; }
.dark .pos-cart-field input, .dark .pos-cart-field select { border-color: rgb(75 85 99); background: rgb(31 41 55); color: white; }
.pos-cart-field-row { display: flex; gap: 0.5rem; }
.pos-cart-field-row .pos-cart-field { flex: 1; }
.pos-cart-field-row .pos-cart-field:first-child { flex: 0 0 6rem; }
.pos-calc-row { display: flex; justify-content: space-between; font-size: 0.875rem; padding: 0.25rem 0; }
.pos-calc-row.total { font-size: 1.125rem; font-weight: 700; padding: 0.5rem 0; }
.pos-calc-row span:last-child { font-weight: 600; }
.pos-calc-row.total span:last-child { color: var(--primary-600); }
.pos-customer-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap; }
.pos-customer-actions button { padding: 0.375rem 0.75rem; font-size: 0.75rem; border-radius: 0.375rem; border: 1px solid rgb(209 213 219); background: white; cursor: pointer; color: rgb(55 65 81); }
.dark .pos-customer-actions button { border-color: rgb(75 85 99); background: rgb(55 65 81); color: rgb(229 231 235); }
.pos-customer-actions button:hover { background: rgb(243 244 246); }
.dark .pos-customer-actions button:hover { background: rgb(75 85 99); }
.pos-customer-actions .fi-btn { width: auto !important; color: rgb(55 65 81) !important; background: white !important; border: 1px solid rgb(209 213 219) !important; }
.pos-customer-actions .fi-btn:hover { background: rgb(243 244 246) !important; }
.dark .pos-customer-actions .fi-btn { color: rgb(229 231 235) !important; background: rgb(55 65 81) !important; border-color: rgb(75 85 99) !important; }
.dark .pos-customer-actions .fi-btn:hover { background: rgb(75 85 99) !important; }
.pos-add-customer-form { padding: 0.75rem; border-radius: 0.5rem; background: rgb(249 250 251); margin-top: 0.5rem; }
.dark .pos-add-customer-form { background: rgba(31,41,55,0.5); }
.pos-add-customer-form .pos-cart-field { margin-bottom: 0.5rem; }
.pos-add-customer-form .pos-cart-field:last-of-type { margin-bottom: 0.75rem; }
.pos-customer-suggestions { margin-top: 0.5rem; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; background: white; overflow: hidden; }
.dark .pos-customer-suggestions { border-color: rgb(75 85 99); background: rgb(31 41 55); }
.pos-customer-suggestion { width: 100%; padding: 0.625rem 0.75rem; border: 0; border-bottom: 1px solid rgb(229 231 235); background: transparent; text-align: left; cursor: pointer; }
.dark .pos-customer-suggestion { border-bottom-color: rgb(75 85 99); }
.pos-customer-suggestion:last-child { border-bottom: 0; }
.pos-customer-suggestion:hover { background: rgb(249 250 251); }
.dark .pos-customer-suggestion:hover { background: rgb(55 65 81); }
.pos-customer-suggestion-phone { display: block; font-size: 0.875rem; font-weight: 600; color: rgb(17 24 39); }
.dark .pos-customer-suggestion-phone { color: white; }
.pos-customer-suggestion-name { display: block; font-size: 0.75rem; color: rgb(107 114 128); }
.dark .pos-customer-suggestion-name { color: rgb(156 163 175); }
.pos-customer-selected { margin-top: 0.5rem; font-size: 0.75rem; color: rgb(75 85 99); }
.dark .pos-customer-selected { color: rgb(209 213 219); }
.pos-calc-breakdown { border-top: 1px solid rgb(229 231 235); padding-top: 0.5rem; margin-top: 0.5rem; }
.dark .pos-calc-breakdown { border-color: rgba(255,255,255,0.1); }

.pos-lens-tabs { display: flex; gap: 0.5rem; padding: 0.5rem 1rem 0; flex-shrink: 0; border-bottom: 1px solid rgb(229 231 235); background: rgba(249,250,251,0.8); }
.dark .pos-lens-tabs { border-color: rgba(255,255,255,0.1); background: rgba(17,24,39,0.6); }
@media (min-width: 768px) { .pos-lens-tabs { padding: 0.5rem 1.5rem 0; } }
@media (min-width: 1024px) { .pos-lens-tabs { padding: 0.5rem 2rem 0; } }
.pos-lens-tab { padding: 0.5rem 1rem; border-radius: 0.5rem 0.5rem 0 0; font-size: 0.875rem; font-weight: 600; border: 1px solid transparent; border-bottom: none; background: transparent; color: rgb(75 85 99); cursor: pointer; }
.dark .pos-lens-tab { color: rgb(209 213 219); }
.pos-lens-tab.active { background: white; color: var(--primary-600); border-color: rgb(229 231 235); }
.dark .pos-lens-tab.active { background: rgb(31 41 55); border-color: rgba(255,255,255,0.12); color: var(--primary-400); }

.pos-customize { padding: 1rem; flex: 1; overflow-y: auto; }
@media (min-width: 768px) { .pos-customize { padding: 1.5rem; } }
.pos-customize-title { font-size: 1rem; font-weight: 600; margin: 0 0 0.75rem; color: rgb(17 24 39); }
.dark .pos-customize-title { color: white; }
.pos-optical-notice { font-size: 0.8125rem; color: rgb(220 38 38); margin: 0 0 0.75rem; line-height: 1.4; }
.pos-opt-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
/* Lens actions: same primary/danger tokens as Filament category pills & buttons */
.pos-opt-btn { padding: 0.625rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s; }
.pos-opt-btn:not(.outline):not(.danger) { background: var(--primary-600); color: white; border: 1px solid var(--primary-600); }
.pos-opt-btn:not(.outline):not(.danger):hover { background: var(--primary-500); border-color: var(--primary-500); }
.pos-opt-btn:not(.outline):not(.danger).active { background: var(--primary-700); border-color: var(--primary-700); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.35); }
.dark .pos-opt-btn:not(.outline):not(.danger).active { background: var(--primary-600); border-color: var(--primary-500); box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.35); }
.pos-opt-btn.outline { background: white; color: rgb(55 65 81); border: 1px solid rgb(209 213 219); }
.dark .pos-opt-btn.outline { background: rgb(31 41 55); color: rgb(209 213 219); border-color: rgb(75 85 99); }
.pos-opt-btn.outline:hover { background: rgb(243 244 246); border-color: var(--primary-400); color: var(--primary-600); }
.dark .pos-opt-btn.outline:hover { background: rgb(55 65 81); border-color: var(--primary-400); color: var(--primary-400); }
.pos-opt-btn.outline.active { background: rgba(59, 130, 246, 0.12); border-color: var(--primary-500); color: var(--primary-600); }
.dark .pos-opt-btn.outline.active { background: rgba(59, 130, 246, 0.2); border-color: var(--primary-400); color: var(--primary-300); }
.pos-opt-btn.danger { background: var(--danger-600); border: 1px solid var(--danger-600); color: white; }
.pos-opt-btn.danger:hover { background: var(--danger-500); border-color: var(--danger-500); }
.pos-opt-section { margin-top: 1rem; }
.pos-opt-section h4 { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: rgb(55 65 81); margin: 0 0 0.5rem; }
.dark .pos-opt-section h4 { color: rgb(209 213 219); }
.pos-lens-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem; }
.pos-lens-pill { text-align: left; padding: 0.625rem 0.75rem; border-radius: 0.5rem; border: 1px solid rgb(229 231 235); background: white; cursor: pointer; font-size: 0.75rem; font-weight: 500; color: rgb(17 24 39); line-height: 1.35; transition: border-color 0.15s, box-shadow 0.15s; }
.dark .pos-lens-pill { background: rgb(31 41 55); color: rgb(243 244 246); border-color: rgba(255,255,255,0.1); }
.pos-lens-pill:hover { border-color: var(--primary-500); box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.dark .pos-lens-pill:hover { border-color: var(--primary-400); }
.pos-lens-pill .price { display: block; margin-top: 0.25rem; font-weight: 700; color: var(--primary-600); }
.pos-rx-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; margin-bottom: 0.75rem; }
.pos-rx-table th, .pos-rx-table td { border: 1px solid rgb(229 231 235); padding: 0.35rem; text-align: center; }
.dark .pos-rx-table th, .dark .pos-rx-table td { border-color: rgb(75 85 99); }
.pos-rx-table th { background: rgb(249 250 251); font-weight: 600; }
.dark .pos-rx-table th { background: rgb(55 65 81); }
.pos-rx-table select { width: 100%; max-width: 5.5rem; padding: 0.25rem; border-radius: 0.25rem; border: 1px solid rgb(209 213 219); font-size: 0.7rem; background: white; }
.dark .pos-rx-table select { background: rgb(31 41 55); border-color: rgb(75 85 99); color: white; }
.pos-remark-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
@media (min-width: 640px) { .pos-remark-grid { grid-template-columns: repeat(2, 1fr); } }
.pos-remark-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid rgb(229 231 235); cursor: pointer; font-size: 0.8125rem; }
.dark .pos-remark-item { border-color: rgb(75 85 99); }
.pos-remark-item.selected { border-color: var(--primary-500); background: rgba(59, 130, 246, 0.1); }
.dark .pos-remark-item.selected { border-color: var(--primary-400); background: rgba(59, 130, 246, 0.15); }
.pos-customize-footer { margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed rgb(229 231 235); }
.dark .pos-customize-footer { border-color: rgba(255,255,255,0.12); }

.pos-frame-variant-panel { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 0.5rem; border: 1px solid rgb(229 231 235); background: rgb(249 250 251); }
.dark .pos-frame-variant-panel { border-color: rgba(255,255,255,0.12); background: rgba(31,41,55,0.5); }
.pos-frame-variant-panel h4 { margin: 0 0 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: rgb(55 65 81); }
.dark .pos-frame-variant-panel h4 { color: rgb(209 213 219); }
.pos-frame-variant-panel .pos-cart-field { margin-bottom: 0.5rem; }

.pos-modal-overlay { position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; padding: 1rem; }
.pos-modal { width: 100%; max-width: 22rem; background: white; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.dark .pos-modal { background: rgb(31 41 55); }
.pos-modal h3 { margin: 0 0 1rem; font-size: 1rem; font-weight: 600; color: rgb(17 24 39); }
.dark .pos-modal h3 { color: white; }
.pos-modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; flex-wrap: wrap; }
.pos-modal-actions button { flex: 1; min-width: 6rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: 1px solid rgb(209 213 219); background: white; color: rgb(55 65 81); }
.dark .pos-modal-actions button { background: rgb(55 65 81); border-color: rgb(75 85 99); color: rgb(229 231 235); }
.pos-modal-actions button.primary { background: var(--primary-600); border-color: var(--primary-600); color: white; }
.pos-modal-actions button.primary:hover { background: var(--primary-500); }
</style>
@endpush

<x-filament-panels::page fullHeight>
    <div class="pos-page">
        {{-- Top bar --}}
        <div class="pos-topbar">
            <h1>Point of Sale</h1>
            <div class="pos-search-wrap">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="pos-search-icon" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search products..." />
            </div>
        </div>

        {{-- Main: products + cart --}}
        <div class="pos-main">
            {{-- Products + lens customization --}}
            <div class="pos-products">
                <div class="pos-lens-tabs">
                    <button type="button" wire:click="$set('posAreaTab', 'products')" class="pos-lens-tab {{ $posAreaTab === 'products' ? 'active' : '' }}">Products</button>
                    <button type="button" wire:click="$set('posAreaTab', 'customize')" class="pos-lens-tab {{ $posAreaTab === 'customize' ? 'active' : '' }}">Lens customization</button>
                </div>

                @if($posAreaTab === 'products')
                    {{-- Category pills --}}
                    <div class="pos-categories">
                        <button type="button" wire:click="$set('categoryId', null)" class="pos-cat-btn {{ $categoryId === null ? 'active' : '' }}">All</button>
                        @foreach($this->getCategories() as $category)
                            <button type="button" wire:click="$set('categoryId', {{ $category->id }})" class="pos-cat-btn {{ $categoryId == $category->id ? 'active' : '' }}">{{ $category->name }}</button>
                        @endforeach
                    </div>

                    {{-- Product grid --}}
                    <div class="pos-grid-wrap">
                        <div class="pos-grid">
                            @forelse($this->getProducts() as $product)
                                <button type="button" wire:click="addToCart({{ $product->id }})" class="pos-product-card">
                                    <div class="pos-product-img">
                                        <img src="{{ $this->getProductImageUrl($product->image) }}" alt="{{ $product->name }}" />
                                    </div>
                                    <div class="pos-product-info">
                                        <span class="pos-product-name">{{ $product->name }}</span>
                                        <div class="pos-product-prices">
                                            @if($product->original_price)
                                                <span class="pos-product-original">{{ \Illuminate\Support\Number::currency($product->original_price, $this->getDefaultCurrency()) }}</span>
                                            @endif
                                            <span class="pos-product-price">{{ \Illuminate\Support\Number::currency($product->price, $this->getDefaultCurrency()) }}</span>
                                        </div>
                                        <span class="pos-product-stock">Stock: {{ $product->getStockForBranch($this->branchId) }}</span>
                                    </div>
                                </button>
                            @empty
                                <div class="pos-empty">
                                    <x-filament::icon icon="heroicon-o-cube-transparent" class="pos-empty-icon" />
                                    <p>No products found</p>
                                    <p>Try a different search or category</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="pos-customize">
                        <p class="pos-customize-title">Add lenses to the sale</p>
                        <p style="font-size: 0.8125rem; color: rgb(107 114 128); margin: 0 0 1rem;">Add frames from <strong>Products</strong>, then configure lenses here. Each lens is added as its own cart line with price.</p>

                        @php
                            $lensFrameCtx = $this->getLastNonOpticalCartContext();
                            $lensFrameProduct = $lensFrameCtx ? \App\Models\Product::query()->with('attachedProductOptions')->find($lensFrameCtx['item']['product_id'] ?? null) : null;
                            $lensSizes = $lensFrameProduct?->availableSizeOptions() ?? collect();
                            $lensColors = $lensFrameProduct?->availableColorOptions() ?? collect();
                        @endphp
                        <div class="pos-frame-variant-panel">
                            <h4>Frame size &amp; color (for lenses)</h4>
                            @if($lensFrameProduct && ($lensSizes->isNotEmpty() || $lensColors->isNotEmpty()))
                                <p style="font-size: 0.8125rem; margin: 0 0 0.75rem; color: rgb(107 114 128);">Applies to: <strong>{{ $lensFrameProduct->name }}</strong> (last frame in cart). Updates that cart line.</p>
                                @if($lensSizes->isNotEmpty())
                                    <div class="pos-cart-field">
                                        <label>Frame size</label>
                                        <select wire:model.live="lensFrameSizeOptionId">
                                            <option value="">— Select —</option>
                                            @foreach($lensSizes as $opt)
                                                <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if($lensColors->isNotEmpty())
                                    <div class="pos-cart-field">
                                        <label>Frame color</label>
                                        <select wire:model.live="lensFrameColorOptionId">
                                            <option value="">— Select —</option>
                                            @foreach($lensColors as $opt)
                                                <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            @else
                                <p style="font-size: 0.8125rem; margin: 0; color: rgb(107 114 128);">Add a frame from <strong>Products</strong> first, or choose a product that has sizes/colors configured in Inventory.</p>
                            @endif
                        </div>

                        <div class="pos-opt-bar">
                            <button type="button" wire:click="selectOpticalFlowNoRx" class="pos-opt-btn {{ $opticalFlow === 'no_rx' ? 'active' : 'outline' }}">Lens (no prescription)</button>
                            <button type="button" wire:click="selectOpticalFlowWithRx" class="pos-opt-btn {{ $opticalFlow === 'with_rx' ? 'active' : 'outline' }}">Lens (with prescription)</button>
                            <button type="button" wire:click="cancelOpticalFlow" class="pos-opt-btn outline">Reset</button>
                        </div>

                        @if($opticalFlow === 'no_rx')
                            <div class="pos-opt-section">
                                <h4>Lens type remarks</h4>
                                <div class="pos-lens-grid">
                                    @forelse($this->getOpticalNoPrescriptionLenses() as $lens)
                                        <button type="button" wire:click="addOpticalNoPrescriptionLens({{ $lens->id }})" class="pos-lens-pill">
                                            {{ $lens->name }}
                                            <span class="price">{{ \Illuminate\Support\Number::currency($lens->price, $this->getDefaultCurrency()) }}</span>
                                        </button>
                                    @empty
                                        <p style="font-size: 0.875rem; color: rgb(107 114 128);">No options yet. Add items under Optical → Lenses (no Rx).</p>
                                    @endforelse
                                </div>
                            </div>
                        @elseif($opticalFlow === 'with_rx')
                            @if(!$opticalVision)
                                <div class="pos-opt-bar">
                                    <button type="button" wire:click="setOpticalVision('single')" class="pos-opt-btn outline">Single vision</button>
                                    <button type="button" wire:click="setOpticalVision('progressive')" class="pos-opt-btn outline">Progressive vision</button>
                                    <button type="button" wire:click="$set('opticalFlow', null)" class="pos-opt-btn danger">Back</button>
                                </div>
                                <p class="pos-optical-notice">Select single vision or progressive to enter the prescription and lens type.</p>
                            @else
                                <div class="pos-opt-bar">
                                    <button type="button" wire:click="setOpticalVision('single')" class="pos-opt-btn {{ $opticalVision === 'single' ? 'active' : 'outline' }}">Single vision</button>
                                    <button type="button" wire:click="setOpticalVision('progressive')" class="pos-opt-btn {{ $opticalVision === 'progressive' ? 'active' : 'outline' }}">Progressive vision</button>
                                    <button type="button" wire:click="cancelOpticalVisionSelection" class="pos-opt-btn danger">Cancel</button>
                                </div>

                                <p class="pos-optical-notice">Prescription notice: use &quot;—&quot; in dropdowns where a value is unknown. Complete OD / OS and PD before adding to cart.</p>
                                @if($opticalVision === 'progressive')
                                    <p class="pos-optical-notice">Progressive: confirm medium (M) or large (L) frame where required.</p>
                                @endif

                                <div class="pos-opt-section">
                                    <h4>Prescription — {{ $opticalVision === 'progressive' ? 'Progressive' : 'Single vision' }}</h4>
                                    <table class="pos-rx-table">
                                        <thead>
                                            <tr>
                                                <th>Eye</th>
                                                <th>Sph</th>
                                                <th>Cyl</th>
                                                <th>Axis</th>
                                                @if($opticalVision === 'progressive')
                                                    <th>Add</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>@if($opticalVision === 'progressive')OD (Right)@else OD (Right)@endif</td>
                                                <td><select wire:model.live="od_sph">@foreach($this->getOpticalRxSphereOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                <td><select wire:model.live="od_cyl">@foreach($this->getOpticalRxCylinderOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                <td><select wire:model.live="od_axis">@foreach($this->getOpticalRxAxisOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                @if($opticalVision === 'progressive')
                                                    <td><select wire:model.live="od_add">@foreach($this->getOpticalRxAddOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                @endif
                                            </tr>
                                            <tr>
                                                <td>@if($opticalVision === 'progressive')OS (Left)@else OS (Left)@endif</td>
                                                <td><select wire:model.live="os_sph">@foreach($this->getOpticalRxSphereOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                <td><select wire:model.live="os_cyl">@foreach($this->getOpticalRxCylinderOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                <td><select wire:model.live="os_axis">@foreach($this->getOpticalRxAxisOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                @if($opticalVision === 'progressive')
                                                    <td><select wire:model.live="os_add">@foreach($this->getOpticalRxAddOptions() as $val => $lab)<option value="{{ $val }}">{{ $lab }}</option>@endforeach</select></td>
                                                @endif
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="pos-opt-section">
                                    <h4>PD (pupillary distance)</h4>
                                    <div class="pos-opt-bar">
                                        <button type="button" wire:click="setPdMode('one')" class="pos-opt-btn outline {{ $pd_mode === 'one' ? 'active' : '' }}">One PD</button>
                                        <button type="button" wire:click="setPdMode('two')" class="pos-opt-btn outline {{ $pd_mode === 'two' ? 'active' : '' }}">Two PD</button>
                                        <button type="button" wire:click="setPdMode(null)" class="pos-opt-btn outline">Clear</button>
                                    </div>
                                    @if($pd_mode === 'one')
                                        <div class="pos-cart-field" style="max-width: 12rem; margin-top: 0.5rem;">
                                            <label>Single PD (mm)</label>
                                            <input type="text" wire:model="pd_single" placeholder="e.g. 63" />
                                        </div>
                                    @elseif($pd_mode === 'two')
                                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
                                            <div class="pos-cart-field" style="flex: 1; min-width: 8rem;">
                                                <label>OD PD</label>
                                                <input type="text" wire:model="pd_right" placeholder="Right" />
                                            </div>
                                            <div class="pos-cart-field" style="flex: 1; min-width: 8rem;">
                                                <label>OS PD</label>
                                                <input type="text" wire:model="pd_left" placeholder="Left" />
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="pos-opt-section">
                                    <h4>Lens type remarks</h4>
                                    <div class="pos-remark-grid">
                                        @foreach($this->getOpticalPrescriptionRemarks() as $remark)
                                            @php
                                                $rPrice = $opticalVision === 'progressive' ? $remark->price_progressive : $remark->price_single_vision;
                                            @endphp
                                            <label class="pos-remark-item {{ (int) $opticalRemarkId === (int) $remark->id ? 'selected' : '' }}">
                                                <input type="radio" wire:model.live="opticalRemarkId" value="{{ $remark->id }}" style="accent-color: var(--primary-600);" />
                                                <span><strong>{{ $remark->name }}</strong> — {{ \Illuminate\Support\Number::currency($rPrice, $this->getDefaultCurrency()) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if($this->getOpticalPrescriptionRemarks()->isEmpty())
                                        <p style="font-size: 0.875rem; color: rgb(107 114 128);">Add remark options under Optical → Lens remarks (Rx).</p>
                                    @endif
                                </div>

                                <div class="pos-customize-footer">
                                    <x-filament::button wire:click="addOpticalPrescriptionLensToCart" color="primary">
                                        Add prescription lens to cart
                                    </x-filament::button>
                                </div>
                            @endif
                        @else
                            <p style="font-size: 0.875rem; color: rgb(107 114 128);">Choose <strong>Lens (no prescription)</strong> or <strong>Lens (with prescription)</strong> above.</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Cart sidebar --}}
            <div class="pos-cart">
                <div class="pos-cart-header">
                    <h2>Cart <span>({{ $this->getCartCount() }})</span></h2>
                    @if(count($cart) > 0)
                        <x-filament::button color="gray" size="sm" wire:click="clearCart">Clear</x-filament::button>
                    @endif
                </div>

                <div class="pos-cart-items">
                    @forelse($cart as $index => $item)
                        <div class="pos-cart-item">
                            <div class="pos-cart-item-img">
                                <img src="{{ !empty($item['is_optical']) ? $this->getOpticalLineImageUrl() : $this->getProductImageUrl($item['image'] ?? null) }}" alt="{{ $item['name'] }}" />
                            </div>
                            <div class="pos-cart-item-body">
                                <div class="pos-cart-item-name">{{ $item['name'] }}</div>
                                <div class="pos-cart-item-qty">
                                    @if(!empty($item['is_optical']))
                                        <span style="font-size: 0.75rem; color: rgb(107 114 128);">Qty fixed</span>
                                    @else
                                        <button type="button" wire:click="updateCartQuantity({{ $index }}, {{ $item['quantity'] - 1 }})">−</button>
                                        <span>{{ $item['quantity'] }}</span>
                                        <button type="button" wire:click="updateCartQuantity({{ $index }}, {{ $item['quantity'] + 1 }})">+</button>
                                    @endif
                                </div>
                            </div>
                            <div class="pos-cart-item-right">
                                <span class="pos-cart-item-total">{{ \Illuminate\Support\Number::currency($item['price'] * $item['quantity'], $this->getDefaultCurrency()) }}</span>
                                <button type="button" wire:click="removeFromCart({{ $index }})" class="pos-cart-item-remove">
                                    <x-filament::icon icon="heroicon-o-trash" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="pos-cart-empty">
                            <x-filament::icon icon="heroicon-o-shopping-cart" class="pos-cart-empty-icon" />
                            <p>Cart is empty</p>
                            <p>Click products to add</p>
                        </div>
                    @endforelse
                </div>

                <div class="pos-cart-footer">
                    @if($this->isBranchLocked())
                        <div class="pos-cart-field">
                            <label>Branch</label>
                            <input type="text" value="{{ $this->getBranches()->first()?->name ?? 'Assigned branch' }}" disabled />
                        </div>
                    @else
                        <div class="pos-cart-field">
                            <label>Branch <span style="color: var(--danger-500);">*</span></label>
                            <select wire:model.live="branchId">
                                <option value="">Select branch</option>
                                @foreach($this->getBranches() as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Customer --}}
                    <div class="pos-cart-field">
                        <label>Customer</label>
                        @if($showAddCustomerForm)
                            <div class="pos-add-customer-form">
                                <div class="pos-cart-field">
                                    <label>Name *</label>
                                    <input type="text" wire:model="newCustomerName" placeholder="Full name" />
                                </div>
                                <div class="pos-cart-field">
                                    <label>Phone *</label>
                                    <input type="text" wire:model="newCustomerPhone" placeholder="Phone number" />
                                </div>
                                <div class="pos-cart-field">
                                    <label>Address (optional)</label>
                                    <input type="text" wire:model="newCustomerAddress" placeholder="Address" />
                                </div>
                                <div class="pos-cart-field">
                                    <label>TIN (optional)</label>
                                    <input type="text" wire:model="newCustomerTin" placeholder="Tax ID" />
                                </div>
                                <div class="pos-customer-actions">
                                    <x-filament::button color="gray" size="sm" wire:click="addNewCustomer">Add Customer</x-filament::button>
                                    <button type="button" wire:click="cancelAddCustomer">Cancel</button>
                                </div>
                            </div>
                        @else
                            <input
                                type="text"
                                wire:model.live.debounce.150ms="customerSearch"
                                placeholder="Type phone number"
                            />
                            @if($customerSearch !== '')
                                <div class="pos-customer-suggestions">
                                    @forelse($this->getCustomerSuggestions() as $customer)
                                        <button type="button" class="pos-customer-suggestion" wire:click="selectCustomer({{ $customer->id }})">
                                            <span class="pos-customer-suggestion-phone">{{ $customer->phone }}</span>
                                            <span class="pos-customer-suggestion-name">{{ $customer->name ?: 'No name' }}</span>
                                        </button>
                                    @empty
                                        <div class="pos-customer-suggestion">
                                            <span class="pos-customer-suggestion-name">No customer found for this phone</span>
                                        </div>
                                    @endforelse
                                </div>
                            @endif
                            @if($this->getSelectedCustomer())
                                <div class="pos-customer-selected">
                                    Selected: {{ $this->getSelectedCustomer()->phone ?: 'No phone' }}{{ $this->getSelectedCustomer()->name ? ' - ' . $this->getSelectedCustomer()->name : '' }}
                                </div>
                            @endif
                            <div class="pos-customer-actions">
                                <button type="button" wire:click="$set('showAddCustomerForm', true)">Add New Customer</button>
                                <button type="button" wire:click="useWalkInCustomer">Walk-in</button>
                            </div>
                            @if(count($cart) > 0 && $this->getSelectedCustomer()?->telegram_bot_chat_id)
                                <div class="pos-cart-field" style="margin-top: 0.5rem;">
                                    <x-filament::button color="gray" size="sm" wire:click="sendCartToTelegram" icon="heroicon-o-paper-airplane">
                                        Send cart to Telegram
                                    </x-filament::button>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Discount --}}
                    <div class="pos-cart-field pos-cart-field-row">
                        <div class="pos-cart-field">
                            <label>Discount Type</label>
                            <select wire:model.live="posData.discountType">
                                <option value="fixed">Fixed</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </div>
                        <div class="pos-cart-field">
                            <label>Discount {{ ($posData['discountType'] ?? 'fixed') === 'percentage' ? '(%)' : '(ETB)' }}</label>
                            <input type="number" wire:model.live="posData.discountAmount" step="{{ ($posData['discountType'] ?? 'fixed') === 'percentage' ? '0.1' : '0.01' }}" min="0" placeholder="0" />
                        </div>
                    </div>

                    {{-- Shipping --}}
                    <div class="pos-cart-field">
                        <label>Shipping</label>
                        <input type="number" wire:model.live="posData.shippingAmount" step="0.01" min="0" placeholder="0" />
                    </div>

                    {{-- Tax --}}
                    <div class="pos-cart-field">
                        <label>Tax</label>
                        <select wire:model.live="posData.taxTypeId">
                            <option value="">No tax</option>
                            @foreach($this->getActiveTaxTypes() as $taxType)
                                <option value="{{ $taxType->id }}">{{ $taxType->name }} ({{ $taxType->rate }}%)</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Payment Type --}}
                    <div class="pos-cart-field">
                        <label>Payment Type <span style="color: var(--danger-500);">*</span></label>
                        <select wire:model.live="posData.paymentTypeId">
                            <option value="">Select payment type</option>
                            @foreach($this->getActivePaymentTypes() as $paymentType)
                                <option value="{{ $paymentType->id }}">{{ $paymentType->name }}</option>
                            @endforeach
                        </select>
                        @if(count($this->getActivePaymentTypes()) === 0)
                            <p style="font-size: 0.75rem; color: rgb(107 114 128); margin-top: 0.25rem;">Add payment types in Settings → Payment Types</p>
                        @endif
                    </div>

                    {{-- Calculation breakdown --}}
                    <div class="pos-calc-breakdown">
                        <div class="pos-calc-row">
                            <span>Subtotal</span>
                            <span>{{ \Illuminate\Support\Number::currency($this->getSubtotal(), $this->getDefaultCurrency()) }}</span>
                        </div>
                        @if($this->getDiscountValue() > 0)
                            <div class="pos-calc-row">
                                <span>Discount</span>
                                <span>-{{ \Illuminate\Support\Number::currency($this->getDiscountValue(), $this->getDefaultCurrency()) }}</span>
                            </div>
                        @endif
                        @if(($posData['shippingAmount'] ?? 0) > 0)
                            <div class="pos-calc-row">
                                <span>Shipping</span>
                                <span>{{ \Illuminate\Support\Number::currency($posData['shippingAmount'] ?? 0, $this->getDefaultCurrency()) }}</span>
                            </div>
                        @endif
                        @if($this->getTaxValue() > 0)
                            <div class="pos-calc-row">
                                <span>Tax</span>
                                <span>{{ \Illuminate\Support\Number::currency($this->getTaxValue(), $this->getDefaultCurrency()) }}</span>
                            </div>
                        @endif
                        <div class="pos-calc-row total">
                            <span>Total</span>
                            <span>{{ \Illuminate\Support\Number::currency($this->getFinalTotal(), $this->getDefaultCurrency()) }}</span>
                        </div>
                    </div>
                </div>

                <div class="pos-cart-checkout">
                    <x-filament::button wire:click="checkout" size="lg" :disabled="count($cart) === 0 || !($posData['paymentTypeId'] ?? null)">Complete Sale</x-filament::button>
                </div>
            </div>
        </div>
    </div>

    @if($variantModalProductId)
        @php $vp = $this->getVariantModalProduct(); @endphp
        @if($vp)
            <div class="pos-modal-overlay" wire:click.self="cancelVariantModal" wire:key="pos-variant-modal-{{ $variantModalProductId }}">
                <div class="pos-modal" wire:click.stop>
                    <h3>{{ $vp->name }}</h3>
                    <p style="font-size: 0.8125rem; color: rgb(107 114 128); margin: 0 0 1rem;">Choose size and/or color, then add to cart.</p>
                    @php
                        $vSizes = $vp->availableSizeOptions();
                        $vColors = $vp->availableColorOptions();
                    @endphp
                    @if($vColors->count() > 1)
                        <div class="pos-cart-field">
                            <label>Color <span style="color: var(--danger-500);">*</span></label>
                            <select wire:model.live="variantPickColorId">
                                <option value="">— Select —</option>
                                @foreach($vColors as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if($vSizes->count() > 1)
                        <div class="pos-cart-field">
                            <label>Size <span style="color: var(--danger-500);">*</span></label>
                            <select wire:model.live="variantPickSizeId">
                                <option value="">— Select —</option>
                                @foreach($vSizes as $opt)
                                    <option value="{{ $opt->id }}">{{ $opt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="pos-modal-actions">
                        <button type="button" class="primary" wire:click="confirmVariantAddToCart">Add to cart</button>
                        <button type="button" wire:click="cancelVariantModal">Cancel</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</x-filament-panels::page>

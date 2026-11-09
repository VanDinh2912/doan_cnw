# Web POS — Design System (project source of truth)

> **Scope.** This file is the authoritative UI contract for this Laravel + Blade +
> Bootstrap 5 application. `MASTER.md` in this folder is the raw generator output;
> where the two disagree, **this file wins** and the reason is recorded below.

---

## 1. Why this exists

Before this system, every page invented its own UI: `master.blade.php` used a
primary-blue navbar, `dangnhap.blade.php` was a standalone page with inline CSS
(`#2563eb`, Arial), product thumbnails were sized with inline `style="height:40px"`,
and `thongke` pulled Chart.js from a CDN with hardcoded `#0d6efd` / `#198754`.
The result was visual drift and an app that broke without internet.

This system fixes that with **one token layer** consumed by every page.

---

## 2. Corrections to the generated `MASTER.md`

The generator produced a *landing page* design system. The following are
deliberate overrides:

| Generated | Overridden to | Reason |
|---|---|---|
| Pattern: **Hero + Features + CTA** | **Topbar + grouped sidebar + dense content** | A POS has no hero or conversion funnel. It is a data-entry tool. |
| `.btn-primary { background: #EA580C }` | `--color-primary` = `#2563EB` | Generator contradicted its own palette table and repurposed the accent orange. Orange is reserved for warnings only. |
| `.card:hover { transform: translateY(-2px) }` | No transform; optional `.card-interactive` changes border + shadow only | Cards are containers here, not links. A lift implies a click target that does not exist and causes jitter in table layouts. |
| GSAP scroll-reveal motion | **Removed** | Not a dependency; animation on a cashier screen adds latency and distraction. Motion limited to 120–180ms state transitions. |
| `.card { background: #F8FAFC }` | `--color-surface` = `#FFFFFF` on `--color-bg` `#F8FAFC` | Generator inverted its own palette. Cards must be lighter than the page. |
| Radius 12–16px | 4–10px | Dense dashboard data reads better with tighter corners. |

---

## 3. Token architecture

Three layers, defined in `resources/css/pos-tokens.css`:

```
PRIMITIVE  (raw values, never used by components)
    --prim-blue-600: #2563eb
        ↓
SEMANTIC   (purpose, consumed by components)
    --color-primary: var(--prim-blue-600)
        ↓
BOOTSTRAP BRIDGE  (remaps --bs-* so existing markup inherits)
    --bs-primary: var(--color-primary)
```

**Rule:** no hex value may appear below the primitive section. The
`validate-tokens` idea from `design-system/references/` applies by inspection:
search the CSS for `#[0-9a-f]{3,6}` — the only hits must be in layer 1.

---

## 4. Colour

| Semantic token | Value | Use |
|---|---|---|
| `--color-primary` | `#2563EB` | Primary actions, active nav, links |
| `--color-primary-hover` | `#1D4ED8` | Hover/active state |
| `--color-primary-soft` | `#EFF6FF` | Active nav bg, info alerts |
| `--color-success` | `#16A34A` | Payment success, in-stock badge |
| `--color-warning` | `#F59E0B` | Low-stock badge, pending states |
| `--color-danger` | `#DC2626` | Out-of-stock, destructive actions, errors |
| `--color-bg` | `#F8FAFC` | Page background |
| `--color-surface` | `#FFFFFF` | Cards, table, sidebar, topbar |
| `--color-fg` | `#1E293B` | Primary text |
| `--color-fg-muted` | `#475569` | Secondary text, labels |
| `--color-border` | `#E2E8F0` | Dividers, card borders |

### Business-critical constraint

The stock badge tri-state is **domain logic, not decoration**:

| Stock | Class | Meaning |
|---|---|---|
| `soluong === 0` | `text-bg-danger` | Out of stock |
| `soluong <= soluong_toithieu` | `text-bg-warning` | At/below threshold |
| otherwise | `text-bg-success` | Normal |

These map to `resources/views/sanpham/danhsach.blade.php` and
`tongquan.blade.php`. **Never repurpose these three colours.** Badges use a soft
background with a dark text of the same hue (e.g. `#FEE2E2` on `#B91C1C`) so they
stay legible without competing with primary actions.

---

## 5. Typography

| Role | Font | Size token |
|---|---|---|
| Headings | Outfit | `--text-xl` (22px) page, `--text-lg` (18px) section |
| Body / table | Work Sans | `--text-base` (14px) / `--text-sm` (13px) |
| Meta / badges | Work Sans | `--text-xs` (12px) |
| Numeric | — | `font-variant-numeric: tabular-nums` on `.table`, `.money`, `.stat-value` |

**Tabular numerals are mandatory** for any column of money or quantity — without
them, prices in a table do not align and scanning becomes slow.

---

## 6. Spacing & density

Density dial **8/10**. Scale: `2 / 4 / 8 / 12 / 16 / 24 / 32` px.

| Context | Value |
|---|---|
| Table cell padding | 8px × 12px |
| Card padding | 16px |
| Content area padding | 24px (16px below 992px) |
| Form control height | 36px (30px for `.btn-sm`) |

---

## 7. Components

### 7.1 App shell

```
┌──────────────────────────────────────────────────┐
│ app-topbar  (56px, sticky, white, 1px border)    │
├────────────┬─────────────────────────────────────┤
│ app-sidebar│ app-main                            │
│ 232px      │ padding 24px                        │
│ sticky     │                                     │
│ grouped    │                                     │
│ nav        │                                     │
└────────────┴─────────────────────────────────────┘
```

- Nav items are grouped by workflow with `.app-nav-label`
  (Tổng quan / Bán hàng / Kho & hàng hóa / Quản trị).
- Active state = `--color-primary-soft` background + `--color-primary` text +
  semibold weight. Never use a filled primary background for nav — it competes
  with the actual primary action on the page.
- Below 992px the sidebar becomes a horizontal wrapped bar above the content.

### 7.2 Page header

Every list/form page opens with:

```blade
<div class="page-header">
    <div>
        <h1 class="page-title">Tiêu đề</h1>
        <p class="page-subtitle">Mô tả ngắn</p>
    </div>
    <a class="btn btn-primary" href="...">Hành động chính</a>
</div>
```

One primary action per page, right-aligned.

### 7.3 Stat card (dashboard)

`.stat-card` → `.stat-label` (uppercase 12px muted) + `.stat-value`
(28px Outfit bold, tabular). Used in `tongquan.blade.php`.

### 7.4 Table

- `<thead>` uses `--table-head-bg` (`#F1F5F9`), 12px uppercase semibold, nowrap.
- Rows use `table-hover`; hover bg `#F8FAFC`.
- Product images use `.table-thumb` (36×36, `object-fit: cover`, 4px radius) —
  **never** inline `width`/`height` styles.
- Money columns right-aligned with `.money`; quantity/status centred.
- Any table with `.js-data-table` is auto-initialised with Vietnamese locale.

### 7.5 Badges

`.badge` + `.text-bg-{success|warning|danger}` → soft background, dark same-hue
text. Never solid saturated fills; they dominate the table.

### 7.6 Alerts

`alert-success` / `alert-warning` / `alert-danger` use the soft status background
with a 1px border of the strong hue. `master.blade.php` renders session flash and
validation errors automatically with an icon + list.

### 7.7 Buttons

| Class | Use |
|---|---|
| `btn btn-primary` | Main action (Thanh toán, Thêm, Lưu) |
| `btn btn-success` | Confirm payment received |
| `btn btn-outline-primary` | Secondary per-row action (Sửa) |
| `btn btn-outline-secondary` | Neutral/back (Tem, Quay lại) |
| `btn btn-outline-danger` | Destructive (Xóa, Hủy chờ) |
| `btn btn-sm` | Table row actions |

No `translateY` on hover. Only colour/border/shadow transition, 120ms.

---

## 8. Focus & accessibility

- Single focus ring: `0 0 0 3px rgba(37,99,235,.25)` + primary border.
- Applied to links, buttons, inputs, selects via `:focus-visible`, and to
  Bootstrap controls via `box-shadow`.
- Text contrast ≥ 4.5:1 (`#1E293B` on `#FFFFFF` ≈ 13:1; `#475569` on `#FFFFFF` ≈ 7:1).
- `prefers-reduced-motion: reduce` collapses all transitions to 0.01ms.
- Icon-only controls (logout, modal close) carry `aria-label`.

---

## 9. Print (must not regress)

`donhang/hoadon.blade.php` (K58/K80) and `sanpham/tem.blade.php` are
**standalone documents** with their own `<style>`, `@page` mm sizes, and Arial.
They intentionally do **not** extend `master` and do **not** load this stylesheet.

If a token sheet is ever included on a print page, §18 of `pos-tokens.css`
strips topbar/sidebar and forces black borders + white background under `@media print`.

---

## 10. Asset delivery

All vendor CSS/JS is bundled by Vite and self-hosted — **no CDN**.

| Asset | Source |
|---|---|
| Bootstrap 5.3 | `resources/js/app.js` |
| DataTables 2.3 + Bootstrap 5 skin | `resources/js/app.js` |
| Font Awesome 6.7 | `resources/js/app.js` |
| jQuery 3.7 | `resources/js/app.js` (exposed as `window.$`) |
| Chart.js 4.4 | `resources/js/app.js` (exposed as `window.Chart`) |

Layouts include `@vite(['resources/css/app.css', 'resources/js/app.js'])`.
Rebuild after CSS/JS changes: `npm run build`.

---

## 11. Page overrides

Check `design-system/webpos/pages/<page>.md` before styling a page; if absent,
follow this document. Create an override only for a genuine deviation (e.g. the
POS two-column layout), and record the reason.

---

## 12. Checklist before shipping UI

- [ ] No hex value outside the primitive layer of `pos-tokens.css`
- [ ] No inline `width`/`height`/`color` on images or text
- [ ] No CDN `<link>` or `<script>` in any view
- [ ] Stock badges still use danger/warning/success semantics
- [ ] Tables use `.money` / right alignment for currency
- [ ] Print views untouched
- [ ] `npm run build` succeeds
- [ ] `php artisan test --filter=ExampleTest` passes
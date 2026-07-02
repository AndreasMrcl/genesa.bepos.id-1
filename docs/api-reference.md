# Leavy Cashier API Reference v1

Dokumentasi API untuk aplikasi kasir mobile (Flutter). Audience: mobile developer.

---

> ## 🆕 Update 2026-07-02 — Cart lock saat pembayaran online tertunda
>
> Perubahan perilaku yang **wajib ditangani mobile**:
>
> 1. **🔒 Cart terkunci saat ada pending online order.** Selama sebuah cart punya order online yang belum selesai (`status` = `null` atau `pending`), **semua** endpoint mutasi cart menolak dengan **`409 payment_pending`**:
>    - `POST /cart/items`, `PATCH /cart/items/{id}`, `DELETE /cart/items/{id}`, `DELETE /cart`
>    - `POST /orders/checkout` dengan `payment_method` **`cash`/`edc`** (online tetap boleh — me-reuse pending yang sama).
> 2. **🆕 Endpoint baru:** `POST /orders/{id}/cancel-online` — membatalkan pembayaran online tertunda & membuka kunci cart. Aman terhadap race (rekonsiliasi ke Midtrans dulu).
> 3. **♻️ `resume-online` sekarang rekonsiliasi dulu** & menambah field **`settled`** di response (cek `settled` sebelum buka snap). Lihat [resume-online](#post-ordersidresume-online).
> 4. **🛡️ `checkout` menolak `409 cart`** kalau cart sudah punya order committed (cegah double order/double potong stok). Mobile harus `GET /cart` untuk memuat ulang.
>
> **Aksi mobile:** saat dapat `409 payment_pending`, jangan tampilkan sebagai bug. Arahkan user ke pilihan **Lanjutkan bayar** (`resume-online`) atau **Batalkan** (`cancel-online`). Detail di [Cart](#cart), [checkout](#post-orderscheckout), dan [cancel-online](#post-ordersidcancel-online).

---

## Daftar Isi

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Format request & response](#format-request--response)
4. [Error handling](#error-handling)
5. [Endpoint reference](#endpoint-reference)
   - [Auth](#auth)
   - [Master / Bootstrap](#master--bootstrap)
   - [Cart](#cart)
   - [Order](#order)
   - [Open Bill](#open-bill)
   - [Receipt](#receipt)
   - [History](#history)
   - [Settlement (Shift)](#settlement-shift)
   - [Webhook](#webhook)
6. [Flow / Recipes](#flow--recipes)
7. [Glossary](#glossary)

---

## Overview

- **Base URL (dev)**: `http://genesa.bepos.id`
- **Base URL (prod)**: TBA
- **Versioning**: prefix `/api/v1/`
- **Protocol**: HTTPS (prod), HTTP (dev)
- **Data format**: JSON
- **Timezone**: ISO 8601 dengan offset (`+07:00` Asia/Jakarta)
- **Currency unit**: Integer Rupiah (tanpa desimal). `25000` = Rp 25.000

### Domain singkat

- **Store** — 1 user owner = 1 store. Mobile selalu beroperasi di scope store user yang login.
- **Cart** — wadah item sebelum jadi Order. Tipenya: *draft* (akan di-checkout) atau *open-bill* (meja yang sudah buka tagihan).
- **Order** — transaksi yang sudah masuk antrian/settle. `status` dari Midtrans: `null` (pending online), `pending`, `settlement` (sukses), `expire`.
- **Settlement** — shift kasir (buka-tutup drawer). Order baru hanya bisa dibuat jika ada shift aktif.
- **History** — Order yang sudah di-archive (final, drawer-locked).
- **Chair** — meja fisik di store. Dipakai untuk open-bill & QR customer.

---

## Authentication

API memakai **Laravel Sanctum personal access token**.

### Login

```
POST /api/v1/auth/login
```

Setelah sukses, server return token. Mobile simpan token di **secure storage** (Keychain di iOS, EncryptedSharedPreferences/Keystore di Android — gunakan `flutter_secure_storage`).

### Pakai token

Setiap request authenticated kirim header:

```
Authorization: Bearer <token>
Accept: application/json
```

> ⚠️ Header `Accept: application/json` **WAJIB**. Tanpa ini, error validasi Laravel akan return HTML redirect, bukan JSON.

### Logout

```
POST /api/v1/auth/logout
```

Server menghapus token yang sedang dipakai. Mobile hapus juga dari secure storage.

### Token expiry

Default tanpa expiry. Kalau backend set `'expiration'` di `config/sanctum.php`, token bisa expire. Saat dapat response 401 `Unauthenticated`, mobile harus arahkan ke login screen.

---

## Format request & response

### Sukses (semua 2xx kecuali 204)

```json
{
  "data": {
    /* payload */
  },
  "meta": {
    "server_time": "2026-05-22T10:00:00+07:00"
  }
}
```

- `data` — payload utama. Bentuknya berbeda per endpoint (object atau array).
- `meta.server_time` — selalu ada. Bermanfaat untuk delta sync & display.
- `meta.pagination` — ada di endpoint paginated.

### Sukses tanpa body

Beberapa endpoint return `204 No Content` (mis. logout, revoke device). Body kosong.

### Error

```json
{
  "errors": {
    "<key>": ["<pesan 1>", "<pesan 2>"]
  }
}
```

- `<key>` — nama field (validasi) atau tag domain (`credentials`, `store`, `cart`, `stock`, `shift`, dst).
- Value selalu array of string.

---

## Error handling

| Status | Arti | Aksi mobile |
|---|---|---|
| 200 / 201 | Sukses | Lanjut |
| 204 | Sukses tanpa body | Lanjut |
| 400 | Bad request (mis. webhook payload incomplete) | Display generic error |
| 401 | Unauthenticated | Hapus token, arahkan ke login |
| 403 | Forbidden (store belum aktif, dsb) | Display pesan, mungkin arahkan ke kontak admin |
| 404 | Resource tidak ditemukan | Display "tidak ditemukan" |
| 409 | Conflict (mis. open-bill chair sudah ada, shift belum ditutup, **cart terkunci pembayaran online** → key `payment_pending`) | Display pesan ke user — bukan bug, butuh aksi user |
| 422 | Validation failed / business rule violated | Display per-field error dari `errors` |
| 500 | Server error | Display generic error, log untuk Sentry/Crashlytics |

---

## Endpoint reference

> Tabel ringkas. Detail tiap endpoint di bawah.

| Method | Path | Auth | Modul |
|---|---|:-:|---|
| POST | /auth/login | ❌ | Auth |
| POST | /auth/logout | ✅ | Auth |
| GET | /auth/me | ✅ | Auth |
| GET | /auth/devices | ✅ | Auth |
| DELETE | /auth/devices/{id} | ✅ | Auth |
| GET | /bootstrap | ✅ | Master |
| GET | /store-config | ✅ | Master |
| GET | /cart | ✅ | Cart |
| POST | /cart/items | ✅ | Cart |
| PATCH | /cart/items/{id} | ✅ | Cart |
| DELETE | /cart/items/{id} | ✅ | Cart |
| DELETE | /cart | ✅ | Cart |
| GET | /orders | ✅ | Order |
| GET | /orders/{id} | ✅ | Order |
| POST | /orders/sync-status | ✅ | Order |
| POST | /orders/checkout | ✅ | Order |
| POST | /orders/{id}/confirm-online | ✅ | Order |
| POST | /orders/{id}/resume-online | ✅ | Order |
| POST | /orders/{id}/cancel-online 🆕 | ✅ | Order |
| POST | /orders/{id}/archive | ✅ | Order |
| DELETE | /orders/{id} | ✅ | Order |
| GET | /orders/{id}/receipt | ✅ | Receipt |
| GET | /open-bills | ✅ | Open Bill |
| POST | /open-bills | ✅ | Open Bill |
| DELETE | /open-bills/{cartId} | ✅ | Open Bill |
| GET | /history | ✅ | History |
| GET | /history/{id} | ✅ | History |
| GET | /history/export | ✅ | History |
| GET | /history/exports/{file} | signed | History |
| GET | /settlements | ✅ | Settlement |
| GET | /settlements/active | ✅ | Settlement |
| GET | /settlements/{id} | ✅ | Settlement |
| POST | /settlements/start | ✅ | Settlement |
| POST | /settlements/close | ✅ | Settlement |
| DELETE | /settlements/{id} | ✅ | Settlement |
| POST | /webhooks/midtrans | ❌ | Webhook |

---

### Auth

#### POST `/auth/login`

Login user owner & terbitkan token.

**Request body**
```json
{
  "email": "owner@example.com",
  "password": "secret",
  "device_name": "Samsung A52 - Kasir Pagi"
}
```

| Field | Tipe | Required | Catatan |
|---|---|---|---|
| email | string (email) | ✅ | |
| password | string | ✅ | |
| device_name | string (max 255) | ✅ | Disimpan sebagai nama token. Pakai nama device + role/shift untuk mudah revoke. |

**Response 200**
```json
{
  "data": {
    "token": "1|aBcDeF0123...",
    "user":  { "id": 1, "name": "Andi", "email": "owner@example.com", "level": "owner" },
    "store": {
      "id": 1, "name": "Warung Andi", "location": "Jl. Mawar 1", "phone": "0812...",
      "status": "Settlement",
      "config": {
        "currency": "IDR",
        "tax_percent": "0.00", "tax_active": false,
        "service_percent": "0.00", "service_active": false,
        "receipt_header": "Selamat datang!", "receipt_footer": "Terima kasih",
        "min_stock_alert": 5
      }
    }
  },
  "meta": { "server_time": "..." }
}
```

**Error**
- 401 — credentials salah
- 403 — store belum aktif / user belum punya store

#### POST `/auth/logout`

Revoke token yang sedang dipakai.

**Response 204 No Content**

#### GET `/auth/me`

Refresh profile + store info. Mobile panggil ini saat re-open app untuk validasi token masih aktif.

**Response 200** — sama dengan `/login` tapi tanpa field `token`.

#### GET `/auth/devices`

List token aktif user (untuk fitur keamanan).

**Response 200**
```json
{
  "data": {
    "devices": [
      { "id": 12, "name": "Samsung A52 - Kasir Pagi",
        "last_used_at": "2026-05-22T09:50:00+07:00",
        "created_at": "2026-05-20T08:00:00+07:00",
        "current": true }
    ]
  }
}
```

#### DELETE `/auth/devices/{id}`

Revoke token tertentu (mis. "Logout dari device lain").

**Response 204** atau **404** kalau tidak ditemukan.

---

### Master / Bootstrap

#### GET `/bootstrap`

One-shot endpoint untuk semua master data yang dibutuhkan UI mobile. Panggil sekali setelah login. Mobile cache hasilnya di SQLite/Hive.

**Response 200**
```json
{
  "data": {
    "menus": [
      { "id": 1, "name": "Es Teh", "price": 5000,
        "description": null, "category_id": 2,
        "has_variety": true, "varieties": ["normal","manis","tawar"],
        "img": "http://localhost:8000/storage/menu/1.jpg",
        "updated_at": "2026-05-22T10:00:00+07:00" }
    ],
    "categories": [ { "id": 2, "name": "Minuman", "desc": null, "updated_at": "..." } ],
    "showcases":  [ { "id": 1, "name": "Cabang Utama", "img": "...", "updated_at": "..." } ],
    "discounts":  [ { "id": 1, "name": "Member 10%", "percentage": 10.0, "updated_at": "..." } ],
    "chairs":     [ { "id": 1, "name": "Meja 1", "updated_at": "..." } ],
    "store_config": {
      "currency": "IDR",
      "tax_percent": "0.00", "tax_active": false,
      "service_percent": "0.00", "service_active": false,
      "receipt_header": "...", "receipt_footer": "...",
      "min_stock_alert": 5,
      "updated_at": "..."
    }
  },
  "meta": { "server_time": "..." }
}
```

**Strategi cache di mobile**: hasil bootstrap disimpan persistent. Refresh otomatis saat:
- Login
- Pull-to-refresh di halaman menu
- Aplikasi resume dari background lebih dari N menit

#### GET `/store-config`

Hanya store config (lebih ringan dari `/bootstrap`). Pakai kalau hanya butuh setting tax/service/receipt.

**Response 200**
```json
{ "data": { "store_config": { /* sama dengan field di bootstrap */ } } }
```

---

### Cart

Cart = wadah item sebelum jadi Order. Tiap user punya 1 *draft* cart aktif. Open-bill cart bisa di-append item lagi (kembali ke meja).

> ### 🔒 Cart lock (update 2026-07-02)
>
> Kalau cart punya **pending online order** (order dengan `payment_type=online` dan `status` = `null`/`pending`), cart itu **dikunci**. Endpoint mutasi berikut akan menolak dengan **`409 payment_pending`**:
>
> - `POST /cart/items` (tambah item)
> - `PATCH /cart/items/{id}` (ubah item)
> - `DELETE /cart/items/{id}` (hapus item)
> - `DELETE /cart` (reset)
>
> `GET /cart` **tetap boleh** (menampilkan isi cart + status terkunci).
>
> **Alasan:** mencegah total cart berubah setelah `snap_token` dibuat (customer bisa kurang/lebih bayar) dan mencegah order online yatim.
>
> **Cara buka kunci:** selesaikan pembayaran (`confirm-online`) atau batalkan (`cancel-online`).
>
> **Bentuk error:**
> ```json
> { "errors": { "payment_pending": ["Ada pembayaran online yang belum selesai untuk keranjang ini. Selesaikan atau batalkan pembayaran dulu."] } }
> ```

#### GET `/cart`

Ambil draft cart aktif user. Server auto-create kalau belum ada.

**Query param**
- `cart_id` (opsional, integer) — kalau diisi, ambil cart open-bill spesifik (harus milik store user & `is_open_bill=true`).

**Response 200**
```json
{
  "data": {
    "cart": {
      "id": 12,
      "is_open_bill": false,
      "chair": null,
      "total_amount": 25000,
      "opened_at": null,
      "items": [
        {
          "id": 31,
          "menu_id": 5, "menu_name": "Kopi Susu",
          "unit_price": 12000,
          "variety": "normal",
          "quantity": 2,
          "notes": "es sedikit",
          "discount_id": null, "discount_name": null, "discount_percentage": null,
          "subtotal": 24000
        }
      ]
    }
  }
}
```

#### POST `/cart/items`

Tambah item ke cart.

**Request body**
```json
{
  "menu_id": 5,
  "quantity": 2,
  "variety": "normal",
  "notes": "es sedikit",
  "discount_id": null,
  "cart_id": null
}
```

| Field | Tipe | Required | Catatan |
|---|---|---|---|
| menu_id | integer | ✅ | Harus exists di menus & milik store user |
| quantity | integer ≥ 1 | ✅ | |
| variety | string (max 50) | optional | Ignored jika `menu.has_variety=false`. Validated against `menu.varieties` |
| notes | string (max 255) | optional | |
| discount_id | integer | optional | Harus exists di discounts |
| cart_id | integer | optional | Kalau diisi, append ke open-bill cart spesifik |

**Behavior**:
- Sistem cek stok via inventory service. Kalau bahan tidak cukup → 422.
- Subtotal = `menu.price × quantity`. Kalau ada discount, kurangi `subtotal × (discount.percentage / 100)`.
- Kalau line item dengan kombinasi `(cart, menu, variety, notes, discount)` yang sama sudah ada → quantity & subtotal di-increment (merge).
- `cart.total_amount` di-increment dengan subtotal item.

**Response 200** — full cart updated (sama shape dengan GET /cart).

**Error 422 stok**
```json
{ "errors": { "stock": ["Stok bahan tidak cukup: Gula, Kopi Bubuk"] } }
```

#### PATCH `/cart/items/{id}`

Update partial item di cart. Berguna untuk tombol +/- quantity, edit notes, ganti diskon.

**Request body** (semua optional)
```json
{ "quantity": 3, "notes": "tanpa gula", "variety": "manis", "discount_id": 1 }
```

**Behavior**:
- Hanya field yang dikirim yang berubah.
- Kalau `quantity` naik, re-validasi stok untuk delta-nya.
- Subtotal & total_amount cart di-recompute.

**Response 200** — full cart updated.

#### DELETE `/cart/items/{id}`

Hapus 1 line item. `cart.total_amount` di-decrement dengan subtotal item.

**Response 200** — full cart updated.

#### DELETE `/cart`

Reset draft cart aktif (hapus semua items, set `total_amount = 0`).

**Response 200** — cart kosong.

---

### Order

#### GET `/orders`

List order aktif di store user (yang belum di-archive).

> ⚠️ Status order pending online (`null`/`pending`) di response **tidak** otomatis di-sync ke Midtrans. Mobile harus panggil `POST /orders/sync-status` untuk sync.

**Response 200**
```json
{
  "data": {
    "orders": [
      {
        "id": 12, "no_order": "ORDER-ABC12345",
        "status": "settlement",
        "payment_type": "cash", "payment_reference": null,
        "layanan": "dine-in", "atas_nama": null, "no_telpon": null,
        "total_amount": 25000,
        "created_at": "2026-05-22T14:30:00+07:00",
        "updated_at": "...",
        "cart": {
          "id": 7,
          "chair": { "id": 1, "name": "Meja 1" },
          "items": [ /* CartItem */ ]
        }
      }
    ]
  }
}
```

#### GET `/orders/{id}`

Detail 1 order.

**Response 200** — single OrderResource (sama shape dengan item di list).

#### POST `/orders/sync-status`

Sync status order pending online dengan Midtrans. Dipanggil saat user pull-to-refresh halaman order.

**Response 200**
```json
{ "data": { "synced": 3 } }
```

#### POST `/orders/checkout`

Checkout draft cart aktif (atau open-bill cart kalau `cart_id` diberi).

**Prasyarat**:
- Ada shift aktif (cek dulu via `GET /settlements/active`).
- Cart tidak kosong.

**Request body**
```json
{
  "payment_method": "cash",
  "cash_received": 50000,
  "payment_reference": null,
  "cart_id": null
}
```

| Field | Tipe | Required | Catatan |
|---|---|---|---|
| payment_method | enum: `cash`, `edc`, `online` | ✅ | |
| cash_received | integer ≥ 0 | required jika cash | Harus ≥ `cart.total_amount` |
| payment_reference | string (max 255) | optional | Untuk EDC: nomor approval. Untuk online: di-set otomatis dari Midtrans |
| cart_id | integer | optional | Kalau open-bill yang di-checkout |

**Response untuk cash / EDC (200)**:
```json
{
  "data": {
    "order": { /* OrderResource lengkap */ },
    "change": 25000
  }
}
```

`change` adalah `cash_received - total_amount` (kalau cash). `null` untuk EDC.

**Response untuk online (200)**:
```json
{
  "data": {
    "snap_token": "abc123...",
    "order": {
      "id": 15,
      "no_order": "ORDER-XYZ67890",
      "total_amount": 25000
    }
  }
}
```

Mobile selanjutnya pakai SDK Midtrans Flutter (`midtrans_sdk` / `midtrans_snap`) dengan `snap_token`. Setelah callback sukses dari SDK, panggil `POST /orders/{id}/confirm-online`.

**Errors umum**
- 409 `shift` — Belum ada shift aktif. Buka shift dulu.
- 🔒 409 `payment_pending` — **(update 2026-07-02)** cart ini punya pembayaran online yang belum selesai. Berlaku **hanya** untuk `payment_method` `cash`/`edc`. Arahkan user untuk `resume-online` atau `cancel-online` dulu. `payment_method=online` **tidak** kena error ini (me-reuse pending order yang sama).
- 🆕 409 `cart` "Keranjang ini sudah punya order. Muat ulang keranjang." — **(update 2026-07-02)** cart sudah punya order committed (mis. sudah di-settle via webhook). Mobile harus **muat ulang** (`GET /cart`) untuk dapat cart baru, lalu ulangi order. Mencegah double order/double potong stok.
- 422 `cart` — Keranjang masih kosong.
- 422 `cash_received` — Uang yang diterima kurang dari total.
- 422 `stock` — Stok bahan tidak cukup (kalau ada race condition antara add to cart & checkout).

#### POST `/orders/{id}/confirm-online`

Konfirmasi pembayaran online setelah Midtrans SDK callback sukses. Server akan re-check ke Midtrans sebagai source of truth.

**Tanpa body**.

**Response 200**:
```json
{ "data": { "order": { /* OrderResource updated */ } } }
```

**Errors**
- 409 `midtrans` — pembayaran belum settle.
- 404 `order` — order tidak ditemukan.

> 💡 Webhook server juga akan update status secara independen. Endpoint ini berguna kalau user explicit menekan tombol "Konfirmasi", tidak menggantikan webhook.

#### POST `/orders/{id}/resume-online`

Untuk order online pending yang ingin dilanjutkan pembayarannya (user kill app sebelum bayar, dst).

> 🔒 **Update 2026-07-02 (rekonsiliasi anti double-payment).** Sebelum membuat snap baru, server mengecek transaksi lama di Midtrans:
> - **Sudah dibayar** (`settlement`/`capture`) → order langsung diselesaikan, **tidak** ada snap baru. Response `settled: true` + `order` (tanpa `snap_token`).
> - **Masih pending** → snap lama di-`expire` dulu (kalau gagal → `409`, tidak lanjut) baru buat snap baru. Response `settled: false` + `snap_token`.
>
> Mobile **wajib** cek field `settled` dulu: kalau `true`, perlakukan sebagai pembayaran sukses (tampilkan struk); kalau `false`, buka snap baru.

**Response 200 (masih perlu bayar → snap baru)**:
```json
{
  "data": {
    "settled": false,
    "snap_token": "fresh-token-xyz",
    "order": { "id": 15, "no_order": "ORDER-NEW00001", "total_amount": 25000 }
  }
}
```

**Response 200 (ternyata sudah dibayar)**:
```json
{ "data": { "settled": true, "order": { /* OrderResource, status settlement */ } } }
```

**Errors**
- 422 `order` — order tidak valid untuk resume (bukan online, atau sudah settle/expire).
- 409 `midtrans` — gagal menutup (expire) snap lama; user diminta coba lagi (mencegah dua snap aktif).

> ⚠️ Pada `settled: false`, `no_order` lama di-replace dengan yang baru. Mobile harus update referensi.

#### POST `/orders/{id}/cancel-online` 🆕

> **Update 2026-07-02.** Batalkan pembayaran **online yang belum selesai** dan **buka kunci cart** (lihat [Cart lock](#cart)).

Dipakai saat user menutup Snap tanpa membayar lalu ingin mengubah pesanan / ganti metode bayar.

**Tanpa body.** `{id}` = id order online pending (`status` = `null`/`pending`, `payment_type=online`).

**Server melakukan rekonsiliasi ke Midtrans dulu (anti lost-payment):**
- Kalau transaksi **ternyata sudah dibayar** (`settlement`/`capture`) → order **diselesaikan** (bukan dihapus): status jadi `settlement`, inventory di-consume, cart baru dibuat. Response `settled: true`.
- Kalau **belum dibayar** → transaksi di-`expire` di Midtrans (snap lama tak bisa dibayar lagi), order dihapus, cart lama terbuka kembali. Response `settled: false`.

**Response 200 (belum dibayar → dibatalkan)**
```json
{ "data": { "settled": false, "message": "Pembayaran online dibatalkan." } }
```

**Response 200 (ternyata sudah dibayar → diselesaikan)**
```json
{ "data": { "settled": true, "order": { /* OrderResource updated, status settlement */ } } }
```

**Errors**
- 422 `order` — tidak ada pembayaran online yang bisa dibatalkan (order tidak ditemukan, bukan online, atau sudah settle/expire).
- 409 `midtrans` — gagal meng-expire transaksi di Midtrans (transaksi masih bisa dibayar). Order **tidak** dihapus demi mencegah lost payment; user diminta coba lagi.

> 💡 Setelah `settled: false`, cart lama (yang tadi terkunci) bisa diubah lagi. Setelah `settled: true`, perlakukan seperti pembayaran online sukses (tampilkan struk, mulai cart baru).

#### POST `/orders/{id}/archive`

Archive order ke History. Setelah archive, order tidak muncul lagi di `/orders`, hanya di `/history`.

**Prasyarat**: ada shift aktif (drawer-locked ke shift saat ini).

**Side effects**:
- Membuat row di `history` dengan data ter-flatten.
- Update `settlement.expected = start_amount + sum(cash payments)` (untuk drawer accuracy).
- Hapus cart & cartMenus.

**Response 200**: `{ "data": { "message": "Order archived successfully" } }`

#### DELETE `/orders/{id}`

Hapus order. Inventory yang sudah dipotong akan di-restore via StockMovement.

**Response 200**: `{ "data": { "message": "Order deleted." } }`

---

### Open Bill

Open bill = cart yang sudah di-assign ke meja, belum di-checkout. Kasir bisa append item ke cart ini lalu checkout kemudian.

#### GET `/open-bills`

List open bill di store.

**Response 200**
```json
{ "data": { "open_bills": [ /* array of CartResource dengan is_open_bill=true */ ] } }
```

#### POST `/open-bills`

Buka tagihan untuk chair tertentu dari draft cart aktif (atau `cart_id` spesifik).

**Request body**
```json
{ "chair_id": 1, "cart_id": null }
```

**Prasyarat**:
- Chair tidak boleh sudah punya open-bill lain (per chair maksimal 1).
- Cart tidak kosong.
- Cart belum punya Order committed.

**Side effects**:
- Cart di-update: `is_open_bill=true`, `opened_at=now`, `chair_id=X`.
- Server create draft cart baru untuk user (supaya UI bisa langsung order pelanggan berikutnya).

**Response 200**: full cart (`is_open_bill=true`).

**Errors**
- 409 `chair` — chair sudah punya open bill.
- 422 `cart` — cart kosong.

#### DELETE `/open-bills/{cartId}`

Cancel open bill (mis. customer batalkan pesanan).

**Side effects**:
- Hapus semua cartMenus, pending orders untuk cart, lalu cart itu sendiri.

**Response 200**: `{ "data": { "message": "Open bill canceled." } }`

---

### Receipt

#### GET `/orders/{id}/receipt`

Return data **terstruktur** untuk dicetak ke thermal printer (Bluetooth/USB) lewat library Flutter seperti `esc_pos_bluetooth` / `esc_pos_utils`.

**Response 200**
```json
{
  "data": {
    "store": {
      "name": "Warung Andi",
      "location": "Jl. Mawar 1",
      "phone": "0812345678",
      "receipt_header": "Selamat datang!",
      "receipt_footer": "Terima kasih"
    },
    "order": {
      "no_order": "ORDER-ABC12345",
      "datetime": "2026-05-22T14:35:12+07:00",
      "layanan": "dine-in",
      "payment_type": "cash",
      "payment_reference": null,
      "chair": "Meja 1"
    },
    "items": [
      {
        "name": "Es Teh", "variety": "manis",
        "discount_name": null, "discount_percentage": null,
        "notes": "es sedikit",
        "quantity": 2, "unit_price": 5000, "subtotal": 10000
      }
    ],
    "totals": {
      "subtotal": 10000,
      "tax_percent": 0.0, "tax_amount": 0,
      "service_percent": 0.0, "service_amount": 0,
      "total": 10000
    }
  }
}
```

> 💡 `tax_amount` & `service_amount` di-compute server-side hanya kalau `tax_active`/`service_active = true` di store config.

---

### History

#### GET `/history`

List history (order yang sudah di-archive). **Paginated**.

**Query params**
- `page` (default 1)
- `per_page` (default 20, max 100)

**Response 200**
```json
{
  "data": [
    {
      "id": 12, "no_order": "ORDER-ABC12345",
      "akun": "Andi", "name": "-",
      "order": "Es Teh - 2 - es sedikit - Kopi Susu - 1 -  - ",
      "payment_type": "cash", "status": "settlement",
      "total_amount": 25000,
      "settlement_id": 7,
      "created_at": "2026-05-22T14:35:12+07:00"
    }
  ],
  "meta": {
    "server_time": "...",
    "pagination": { "current_page": 1, "per_page": 20, "total": 145, "last_page": 8 }
  }
}
```

> ⚠️ Field `order` adalah string ter-concat dengan separator ` - ` (legacy format). Format: `<menu_name> - <quantity> - <notes> - ...`.

#### GET `/history/{id}`

Detail 1 history.

#### GET `/history/export?month=N`

Generate Excel export untuk bulan N (1-12). Server return **signed URL** untuk download.

**Response 200**
```json
{
  "data": {
    "url": "http://genesa.bepos.id/api/v1/history/exports/history_5_aB12Xy9z.xlsx?signature=...&expires=...",
    "expires_in": 600,
    "filename": "history_5_aB12Xy9z.xlsx"
  }
}
```

URL valid 10 menit. Mobile bisa langsung pakai untuk download / share file. Cukup `GET <url>` tanpa token.

---

### Settlement (Shift)

Shift = sesi buka-tutup drawer kasir.

#### GET `/settlements`

List semua settlement (history shift).

#### GET `/settlements/active`

Cek apakah ada shift sedang buka.

**Response 200 (ada shift aktif)**:
```json
{
  "data": {
    "settlement": {
      "id": 5, "user_id": 1,
      "start_time": "2026-05-22T08:00:00+07:00",
      "end_time": null,
      "start_amount": 100000,
      "total_amount": null,
      "expected": 250000,
      "is_active": true,
      "created_at": "..."
    }
  }
}
```

**Response 200 (tidak ada)**:
```json
{ "data": { "settlement": null } }
```

> 💡 Mobile harus cek ini sebelum izinkan tombol Checkout. Kalau `null`, arahkan ke screen "Open Shift".

#### GET `/settlements/{id}`

Detail settlement dengan histories yang masuk dalam shift itu.

#### POST `/settlements/start`

Buka shift baru.

**Request body**
```json
{ "start_amount": 100000 }
```

`start_amount` adalah uang awal di drawer (optional, default 0). `expected` akan ter-set sama dengan `start_amount`.

**Errors**
- 409 `shift` — Shift sebelumnya belum ditutup.

**Response 201**: settlement object.

#### POST `/settlements/close`

Tutup shift aktif.

**Request body**
```json
{ "total_amount": 250000 }
```

`total_amount` = uang aktual di drawer saat tutup shift. Mobile bisa compare dengan `expected` untuk tampilkan selisih.

**Prasyarat**: tidak boleh ada open-bill di store.

**Errors**
- 409 `shift` — tidak ada shift aktif.
- 409 `open_bills` — masih ada open bill. Settle atau cancel dulu via `/open-bills`.

#### DELETE `/settlements/{id}`

Hapus settlement (admin operation).

---

### Webhook

#### POST `/webhooks/midtrans`

> Server-to-server. Tidak dipanggil oleh mobile. Daftarkan URL ini di Midtrans Dashboard sebagai Payment Notification URL.

URL prod: `https://your-domain/api/v1/webhooks/midtrans`

Behavior:
- Verify signature SHA512 (`order_id + status_code + gross_amount + server_key`).
- Update status order, consume inventory kalau settlement, delete kalau expire.

---

## Flow / Recipes

### Recipe 1: Login & init

```
1. POST /auth/login  → simpan token di secure storage
2. GET /bootstrap    → simpan master data di local cache (SQLite/Hive)
3. GET /settlements/active
   - jika null → tampilkan screen "Buka Shift"
   - jika ada → tampilkan dashboard Order
```

### Recipe 2: Buka shift

```
1. GET /settlements/active → null
2. User isi start_amount di form
3. POST /settlements/start { start_amount: 100000 }
4. Navigate ke dashboard Order
```

### Recipe 3: Create Order (cash)

```
1. GET /cart → cart kosong (auto-created)
2. User pilih menu → POST /cart/items { menu_id, quantity, variety, notes }
   (ulangi untuk tiap item)
3. (opsional) PATCH/DELETE /cart/items/{id} → adjust
4. User tekan Checkout, isi payment_method=cash & cash_received
5. POST /orders/checkout
   → response: { order, change }
6. Tampilkan "Pembayaran berhasil, kembalian Rp X"
7. (opsional) GET /orders/{id}/receipt → print thermal
```

### Recipe 4: Create Order (online via Midtrans)

```
1. (sama langkah 1-3 dari Recipe 3)
2. POST /orders/checkout { payment_method: "online" }
   → response: { snap_token, order }
3. Mobile pakai SDK Midtrans Flutter:
   var midtrans = await MidtransSdk.init(...);
   await midtrans.startPaymentUiFlow(token: snap_token);
4. SDK callback dengan status (success/pending/failed):
   - kalau success → POST /orders/{id}/confirm-online
   - kalau gagal/cancel → user bisa retry via POST /orders/{id}/resume-online (dapat snap_token baru)
5. (Webhook server juga akan update status independen)

🔒 CATATAN (update 2026-07-02): setelah langkah 2, cart TERKUNCI.
   Selama order online ini pending, mutasi cart & checkout cash/edc → 409 payment_pending.
   Pilihan user di UI:
   - "Lanjutkan bayar"  → POST /orders/{id}/resume-online (atau checkout online lagi)
   - "Batalkan"         → POST /orders/{id}/cancel-online
       • { settled:false } → cart terbuka lagi, user bebas edit / ganti metode
       • { settled:true }  → ternyata sudah dibayar, treat sebagai sukses
```

### Recipe 5: Open bill workflow

```
1. User pilih menu untuk meja → POST /cart/items (sama seperti biasa)
2. User pilih Open Bill, pilih chair → POST /open-bills { chair_id }
   → cart sekarang is_open_bill=true, draft cart baru dibuat
3. Untuk append item nanti:
   - GET /open-bills → dapat list cart open-bill
   - User pilih → GET /cart?cart_id={id} untuk detail
   - POST /cart/items dengan { cart_id: X, menu_id, ... }
4. Saat customer mau bayar:
   - POST /orders/checkout { cart_id: X, payment_method, ... }
```

### Recipe 6: Tutup shift

```
1. Cek tidak ada open-bill (GET /open-bills harus empty)
2. User input total uang di drawer
3. POST /settlements/close { total_amount }
4. Jika ada open-bill → error 409, tampilkan pesan
5. Setelah tutup, tampilkan summary: start, expected, actual, selisih
```

### Recipe 7: Polling order status

```
- Halaman tabel Order: panggil GET /orders tiap N detik (mis. 15s) saat halaman terbuka
- Tambah Pull-to-refresh yang panggil POST /orders/sync-status DULU,
  lalu GET /orders setelah sync selesai
- Order yang sudah settle stop polling status
```

---

## Glossary

| Term | Arti |
|---|---|
| `no_order` | Nomor order yang ditampilkan di struk. Format: `ORDER-XXXXXXXX` (8 hex chars). Juga jadi `order_id` di Midtrans. |
| `status` | Status order. `null` = pending online (belum bayar), `settlement` = sukses bayar, `pending` = Midtrans pending, `expire` = expired |
| `payment_type` | `cash`, `edc`, `online` |
| `payment_reference` | Untuk EDC: nomor approval/voucher. Untuk online: Midtrans `transaction_id`. |
| `is_open_bill` | `true` = cart sudah di-assign ke meja sebagai tagihan terbuka |
| `payment_pending` 🔒 | Key error `409` (update 2026-07-02). Cart terkunci karena ada order online yang belum selesai. Buka kunci via `confirm-online` / `cancel-online`. |
| pending online order | Order dengan `payment_type=online` dan `status` = `null`/`pending`. Menahan (mengunci) cart-nya sampai dibayar atau dibatalkan. |
| `expected` | Estimasi uang yang seharusnya ada di drawer = `start_amount + sum(cash payments di shift)` |
| `variety` | Varian menu (mis. "manis"/"tawar"). Hanya berlaku jika `menu.has_variety=true`. Default `"normal"`. |
| `subtotal` | Total per line item setelah diskon (kalau ada) |
| `total_amount` | Total cart/order (jumlah semua subtotal) |

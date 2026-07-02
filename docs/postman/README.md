# Postman Collection — Leavy Cashier API v1

File: `leavy-cashier-api.postman_collection.json`

## Cara import ke Postman

1. Buka Postman → klik **Import** (kiri atas).
2. Drag-and-drop file `leavy-cashier-api.postman_collection.json`, atau klik **Choose files**.
3. Setelah terimport, klik nama collection **Leavy Cashier API v1** → tab **Variables**.

## Variabel yang perlu di-set sebelum testing

| Variable | Default | Keterangan |
|---|---|---|
| `base_url` | `http://localhost:8000` | Ganti kalau pakai port / domain lain |
| `email` | `owner@example.com` | Email user yang punya store aktif |
| `password` | `password` | Password user tersebut |
| `device_name` | `Postman Test Device` | Nama device untuk identifikasi token |
| `menu_id` | `1` | ID menu yang valid (lihat dari `GET /bootstrap`) |
| `chair_id` | `1` | ID meja yang valid |
| `discount_id` | (kosong) | Optional, isi ID diskon kalau mau test diskon |

Variabel lain (`token`, `cart_id`, `order_id`, `history_id`, `settlement_id`, `cart_item_id`, `device_id`) akan **otomatis** ter-set saat request relevan dijalankan (lihat tab **Tests** di tiap request).

## Urutan testing yang disarankan

### 1. Auth
1. **Login** → token otomatis tersimpan di variable `token`.
2. **Me** → verifikasi profil & store.
3. **List devices** → lihat token aktif.

### 2. Master
4. **Bootstrap** → dapat list `menus`, `categories`, `chairs`, dst. Catat `id` menu yang ingin dipakai, set `menu_id`.

### 3. Shift (wajib sebelum checkout)
5. **Settlement → Start shift** → buka shift dengan `start_amount`.

### 4. Cart → Order
6. **Cart → Get cart** → otomatis create draft cart, `cart_id` tersimpan.
7. **Cart → Add item** → tambah `menu_id` ke cart. `cart_item_id` tersimpan.
8. (Opsional) **Cart → Update / Delete item** → uji modifikasi.
9. **Order → Checkout (cash)** → settle. `order_id` tersimpan.
10. **Order → Receipt** → lihat data terstruktur struk.
11. **Order → Archive** → pindahkan ke History.

### 5. Open Bill flow
12. **Cart → Get cart** + **Add item** lagi (cart baru otomatis).
13. **Open Bill → Open bill** → assign cart ke chair.
14. **Open Bill → List open bills** → verifikasi.
15. **Open Bill → Cancel** atau **Order → Checkout** (dengan `cart_id` dari open-bill) untuk settle.

### 6. Online payment (butuh Midtrans config aktif)
16. **Order → Checkout (online)** → server return `snap_token`. Mobile asli akan handle via SDK.
17. Setelah Anda asumsikan user bayar di Midtrans Snap → **Order → Confirm online**.
18. Kalau gagal/tertinggal → **Order → Resume online** untuk generate snap_token baru.

### 7. History & Settlement
19. **History → List** → lihat order yang sudah di-archive.
20. **History → Export** → dapat signed URL untuk download Excel.
21. **Settlement → Close shift** → tutup shift.
22. **Auth → Logout** → revoke token.

## Catatan teknis

- Semua request **kecuali Login & Webhook** otomatis pakai `Authorization: Bearer {{token}}` (set di collection-level auth).
- Header `Accept: application/json` di-set otomatis lewat pre-request script (penting agar Laravel return JSON, bukan redirect HTML saat validasi gagal).
- **Webhook Midtrans** punya `signature_key` placeholder. Untuk test asli, hitung: `hash('sha512', $order_id . $status_code . $gross_amount . $server_key)`.
- Endpoint download history export tidak ada di collection karena URL-nya signed dari response `/history/export` — buka URL itu di browser langsung untuk download.

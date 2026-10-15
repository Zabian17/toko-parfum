<x-mail::message>
# Pesanan Berhasil Dibuat 🎉

Halo **{{ $order->customer_name }}**,

Terima kasih telah berbelanja di **Toko Albi Parfum**. Pesanan Anda telah kami terima.

---

## Detail Pesanan

| | |
|---|---|
| **No. Pesanan** | {{ $order->order_number }} |
| **Tanggal** | {{ $order->created_at->format('d M Y, H:i') }} |
| **Metode Pembayaran** | {{ strtoupper($order->payment_method) }} |
| **Status** | Menunggu Konfirmasi |

---

## Produk Dipesan

@foreach ($order->items as $item)
- **{{ $item->product_name }}**{{ $item->variant_name ? ' ('.$item->variant_name.')' : '' }} — {{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }} = **Rp {{ number_format($item->subtotal, 0, ',', '.') }}**
@endforeach

---

## Ringkasan Pembayaran

| | |
|---|---|
| Subtotal | Rp {{ number_format($order->subtotal, 0, ',', '.') }} |
| Ongkos Kirim ({{ strtoupper($order->shipping_courier) }} - {{ $order->shipping_service }}) | Rp {{ number_format($order->shipping_cost, 0, ',', '.') }} |
| **Total** | **Rp {{ number_format($order->total_amount, 0, ',', '.') }}** |

---

## Alamat Pengiriman

{{ $order->customer_name }}
{{ $order->shipping_address }}
{{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}

---

@if ($order->payment_method === 'transfer')
**Cara Pembayaran (Transfer Bank):**
Silakan lakukan transfer ke rekening kami dan upload bukti pembayaran melalui website.
@elseif ($order->payment_method === 'midtrans')
**Silakan selesaikan pembayaran Anda melalui link yang diberikan.**
@endif

Jika ada pertanyaan, hubungi kami melalui email atau WhatsApp.

Salam hangat,
**Tim Toko Albi Parfum**
</x-mail::message>

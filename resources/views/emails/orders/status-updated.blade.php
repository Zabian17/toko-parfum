<x-mail::message>
# Update Status Pesanan

Halo **{{ $order->customer_name }}**,

Ada update untuk pesanan Anda di **Toko Albi Parfum**.

---

## Status Terbaru

| | |
|---|---|
| **No. Pesanan** | {{ $order->order_number }} |
| **Status Pesanan** | @php
    $statusLabel = match ($order->status) {
        'pending'    => '⏳ Menunggu Konfirmasi',
        'confirmed'  => '✅ Dikonfirmasi',
        'processing' => '📦 Sedang Diproses',
        'shipped'    => '🚚 Dikirim',
        'delivered'  => '🎉 Terkirim',
        'cancelled'  => '❌ Dibatalkan',
        default      => ucfirst($order->status),
    };
    echo $statusLabel;
@endphp |
| **Status Pembayaran** | {{ ucfirst($order->payment_status) }} |
@if ($order->tracking_number)
| **No. Resi** | {{ $order->tracking_number }} |
@endif

---

@if ($order->status === 'shipped')
Pesanan Anda telah dikirim melalui **{{ strtoupper($order->shipping_courier) }}** ({{ $order->shipping_service }}).

@if ($order->tracking_number)
Nomor resi pengiriman Anda: **{{ $order->tracking_number }}**

Cek status pengiriman di website {{ strtoupper($order->shipping_courier) }}.
@endif
@elseif ($order->status === 'delivered')
Pesanan Anda telah berhasil diterima. Terima kasih telah berbelanja di Toko Albi Parfum! 🙏
@elseif ($order->status === 'cancelled')
Pesanan Anda telah dibatalkan. Hubungi kami jika ada pertanyaan.
@endif

@if ($order->admin_notes)
**Catatan dari Admin:** {{ $order->admin_notes }}
@endif

---

## Detail Pesanan

| | |
|---|---|
| **Total** | Rp {{ number_format($order->total_amount, 0, ',', '.') }} |
| **Kurir** | {{ strtoupper($order->shipping_courier) }} - {{ $order->shipping_service }} |
| **Alamat** | {{ $order->shipping_address }}, {{ $order->shipping_city }} |

---

Jika ada pertanyaan, hubungi kami melalui email atau WhatsApp.

Salam hangat,
**Tim Toko Albi Parfum**
</x-mail::message>

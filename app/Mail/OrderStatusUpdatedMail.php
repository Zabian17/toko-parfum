<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        $statusLabel = match ($this->order->status) {
            'confirmed'  => 'Dikonfirmasi',
            'processing' => 'Sedang Diproses',
            'shipped'    => 'Dikirim',
            'delivered'  => 'Terkirim',
            'cancelled'  => 'Dibatalkan',
            default      => ucfirst($this->order->status),
        };

        return new Envelope(
            subject: "Update Pesanan #{$this->order->order_number} - {$statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.status-updated',
            with: [
                'order' => $this->order,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

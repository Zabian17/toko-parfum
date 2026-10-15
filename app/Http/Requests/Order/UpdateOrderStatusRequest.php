<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'       => ['sometimes', 'required', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'payment_status'=> ['sometimes', 'required', 'in:unpaid,pending,paid,failed,refunded'],
            'tracking_number'=> ['nullable', 'string', 'max:100'],
            'admin_notes'  => ['nullable', 'string', 'max:1000'],
            'payment_proof'=> ['nullable', 'image', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
        ];
    }
}

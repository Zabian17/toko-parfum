<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:150'],
            'email'                  => ['nullable', 'email', 'max:200', 'unique:customers,email'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'address'                => ['nullable', 'string'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'province'               => ['nullable', 'string', 'max:100'],
            'postal_code'            => ['nullable', 'string', 'max:10'],
            'rajaongkir_city_id'     => ['nullable', 'string', 'max:10'],
            'rajaongkir_province_id' => ['nullable', 'string', 'max:10'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_phone' => 'required|string|exists:users,phone', // رقم هاتف المرسل
            'shipment_type' => 'required|string|max:255',
            'number_of_pieces' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:0.1',
            'product_value' => 'required|numeric|min:0',
        ];
    }
}

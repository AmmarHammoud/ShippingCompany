<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShipmentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return  [
            'recipient_name' => 'sometimes|string|max:255',
            'recipient_phone' => 'sometimes|string|max:20',
            'recipient_location' => 'nullable|string|max:255',
            'recipient_lat' => 'sometimes|numeric|between:-90,90',
            'recipient_lng' => 'sometimes|numeric|between:-180,180',
            'shipment_type' => 'sometimes|string|max:100',
            'number_of_pieces' => 'sometimes|integer|min:1',
            'weight' => 'sometimes|numeric|min:0.1',
            'total_amount' => 'sometimes|numeric|min:0',

        ];
    }
}

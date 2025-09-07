<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentDetailsRequest extends FormRequest
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
        return [
            'shipment_type' => 'required|string|max:255',
            'number_of_pieces' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:0.1',
            'sender_lat' => 'required|numeric|between:-90,90',
            'sender_lng' => 'required|numeric|between:-180,180',
        ];
    }

}

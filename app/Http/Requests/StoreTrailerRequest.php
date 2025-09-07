<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrailerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // فقط المشرف يمكنه إضافة الشاحنات
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'required|string|max:50',
            'capacity_kg' => 'required|numeric|min:0',
            'capacity_m3' => 'required|numeric|min:0',
            'center_to_id' => 'nullable|exists:centers,id',
        ];
    }
}

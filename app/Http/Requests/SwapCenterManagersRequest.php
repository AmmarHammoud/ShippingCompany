<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwapCenterManagersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'swaps' => 'required|array|min:2',
            'swaps.*.manager_id' => 'required|integer|exists:users,id',
            'swaps.*.to_center_id' => 'required|integer|exists:centers,id',
        ];
    }
}

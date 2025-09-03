<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCenterManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:255'],
            'email'        => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $this->route('id')],
            'phone'        => ['sometimes', 'regex:/^(\+?963|0?9)\d{8}$/', 'unique:users,phone,' . $this->route('id')],
            'password'     => ['sometimes', 'string', 'min:6'],
            'center_id'    => ['sometimes', 'exists:centers,id'],
            'is_approved'  => ['sometimes', 'boolean'],
            'active'       => ['sometimes', 'boolean'],
        ];
    }
}

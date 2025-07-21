<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class HandleShipmentOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->role === 'driver';
    }

    public function rules(): array
    {
        return [];
    }
}


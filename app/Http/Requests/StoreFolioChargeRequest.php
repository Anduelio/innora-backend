<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFolioChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'chargeCategoryId' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unitCents' => ['required', 'integer'],
            'serviceDate' => ['sometimes', 'date_format:Y-m-d'],
            'roomNumber' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}

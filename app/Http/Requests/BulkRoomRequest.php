<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'roomTypeId' => ['required', 'integer'],
            'numbers' => ['nullable', 'array'],
            'numbers.*' => ['string', 'max:32'],
            'from' => ['nullable', 'string', 'max:8'],
            'to' => ['nullable', 'string', 'max:8'],
            'floor' => ['nullable', 'string', 'max:32'],
            'building' => ['nullable', 'string', 'max:80'],
        ];
    }
}

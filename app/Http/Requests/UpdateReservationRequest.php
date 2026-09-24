<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'roomId' => ['sometimes', 'string'],
            'guestName' => ['sometimes', 'string', 'max:120'],
            'phonePrefix' => ['sometimes', 'nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'registerCustomer' => ['sometimes', 'boolean'],
            'persons' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'checkIn' => ['sometimes', 'date_format:Y-m-d'],
            'checkOut' => ['sometimes', 'date_format:Y-m-d'],
            'totalCents' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}

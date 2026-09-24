<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'roomId' => ['required_without:roomTypeId', 'nullable', 'string'],
            'roomTypeId' => ['required_without:roomId', 'nullable', 'integer'],
            'children' => ['nullable', 'integer', 'min:0', 'max:6'],
            'expectedArrival' => ['nullable', 'date_format:H:i'],
            'guestName' => ['required', 'string', 'max:120'],
            'phonePrefix' => ['nullable', 'string', 'regex:/^\+\d{1,4}$/', 'required_if:registerCustomer,true,1'],
            'phone' => ['nullable', 'string', 'max:40', 'required_if:registerCustomer,true,1'],
            'registerCustomer' => ['sometimes', 'boolean'],
            'persons' => ['required', 'integer', 'min:1', 'max:12'],
            'source' => ['sometimes', 'string', Rule::in(['DIREKT', 'TELEFON', 'WHATSAPP', 'RECEPSION'])],
            'checkIn' => ['required', 'date_format:Y-m-d'],
            'nights' => ['required', 'integer', 'min:1', 'max:60'],
            'totalCents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'source.in' => __('messages.reservations.invalid_source'),
            'phonePrefix.required_if' => __('messages.reservations.phone_required_to_register'),
            'phonePrefix.regex' => __('messages.reservations.phone_prefix_invalid'),
            'phone.required_if' => __('messages.reservations.phone_required_to_register'),
        ];
    }
}

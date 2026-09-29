<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phonePrefix' => ['nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('messages.guests.name_required'),
            'phonePrefix.regex' => __('messages.guests.phone_prefix_invalid'),
        ];
    }
}

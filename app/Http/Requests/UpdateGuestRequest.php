<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'phonePrefix' => ['sometimes', 'nullable', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'phonePrefix.regex' => __('messages.guests.phone_prefix_invalid'),
        ];
    }
}

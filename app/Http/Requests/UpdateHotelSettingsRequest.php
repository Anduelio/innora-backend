<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'checkInTime' => ['required', 'date_format:H:i'],
            'checkOutTime' => ['required', 'date_format:H:i'],
        ];
    }
}

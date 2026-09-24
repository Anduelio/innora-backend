<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'roomTypeId' => ['sometimes', 'integer'],
            'number' => ['sometimes', 'string', 'max:32'],
            'floor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'building' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}

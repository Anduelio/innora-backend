<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => ['sometimes', 'string', 'max:32'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shortDescription' => ['sometimes', 'nullable', 'string', 'max:180'],
            'baseOccupancy' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'maxAdults' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'maxChildren' => ['sometimes', 'integer', 'min:0', 'max:12'],
            'maxOccupancy' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'basePriceCents' => ['sometimes', 'integer', 'min:0'],
            'sizeM2' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'beds' => ['sometimes', 'array'],
            'beds.*.code' => ['required_with:beds', 'string'],
            'beds.*.quantity' => ['required_with:beds', 'integer', 'min:1', 'max:8'],
            'amenityCodes' => ['sometimes', 'array'],
            'amenityCodes.*' => ['string'],
        ];
    }
}

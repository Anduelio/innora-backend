<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32'],
            'uiType' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
            'shortDescription' => ['nullable', 'string', 'max:180'],
            'baseOccupancy' => ['nullable', 'integer', 'min:1', 'max:12'],
            'maxAdults' => ['required', 'integer', 'min:1', 'max:12'],
            'maxChildren' => ['required', 'integer', 'min:0', 'max:12'],
            'maxOccupancy' => ['required', 'integer', 'min:1', 'max:12'],
            'basePriceCents' => ['nullable', 'integer', 'min:0'],
            'sizeM2' => ['nullable', 'integer', 'min:1', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'beds' => ['nullable', 'array'],
            'beds.*.code' => ['required', 'string'],
            'beds.*.quantity' => ['required', 'integer', 'min:1', 'max:8'],
            'amenityCodes' => ['nullable', 'array'],
            'amenityCodes.*' => ['string'],
        ];
    }
}

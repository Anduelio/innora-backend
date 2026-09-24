<?php

namespace App\Http\Requests;

use App\Enums\OperationalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'roomTypeId' => ['required', 'integer'],
            'number' => ['required', 'string', 'max:32'],
            'floor' => ['nullable', 'string', 'max:32'],
            'building' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'operationalStatus' => ['nullable', Rule::enum(OperationalStatus::class)],
        ];
    }
}

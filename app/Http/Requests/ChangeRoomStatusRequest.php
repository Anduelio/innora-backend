<?php

namespace App\Http\Requests;

use App\Enums\OperationalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeRoomStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'operationalStatus' => ['required', Rule::enum(OperationalStatus::class)],
        ];
    }
}

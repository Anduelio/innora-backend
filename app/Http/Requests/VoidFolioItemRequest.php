<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidFolioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

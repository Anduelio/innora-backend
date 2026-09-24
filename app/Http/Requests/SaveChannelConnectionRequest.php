<?php

namespace App\Http\Requests;

use App\Channels\ChannelCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveChannelConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'providerCode' => ['required', 'string', Rule::in(ChannelCatalog::codes())],
            'isActive' => ['sometimes', 'boolean'],
            'credentials' => ['required', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}

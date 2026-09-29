<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('q')) {
            return;
        }

        $term = trim((string) $this->input('q'));
        $this->merge([
            'q' => $term === '' ? null : $term,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'withPhone' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'no_pagination' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => __('messages.guests.search_too_long'),
        ];
    }
}

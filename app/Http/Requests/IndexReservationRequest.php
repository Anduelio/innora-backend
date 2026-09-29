<?php

namespace App\Http\Requests;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['q', 'search'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $term = trim((string) $this->input($key));
            $merge[$key] = $term === '' ? null : $term;
        }

        if ($this->exists('status')) {
            $merge['status'] = $this->statusList($this->input('status'));
        }

        if ($this->exists('source') && is_string($this->input('source'))) {
            $merge['source'] = ReservationSource::canonicalFilter((string) $this->input('source'));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(ReservationStatus::filterValues())],
            'source' => ['nullable', 'string', Rule::in(ReservationSource::filterValues())],
            'arriving_on' => ['nullable', 'date_format:Y-m-d'],
            'departing_on' => ['nullable', 'date_format:Y-m-d'],
            'in_house_on' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'no_pagination' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => __('messages.reservations.search_too_long'),
            'search.max' => __('messages.reservations.search_too_long'),
            'status.*.in' => __('messages.reservations.invalid_status'),
            'source.in' => __('messages.reservations.invalid_list_source'),
            'from.date_format' => __('messages.reservations.invalid_dates'),
            'to.date_format' => __('messages.reservations.invalid_dates'),
            'arriving_on.date_format' => __('messages.reservations.invalid_dates'),
            'departing_on.date_format' => __('messages.reservations.invalid_dates'),
            'in_house_on.date_format' => __('messages.reservations.invalid_dates'),
        ];
    }

    /**
     * @return list<string>|null
     */
    private function statusList(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $canonical = [];

        foreach ($parts as $part) {
            if (! is_scalar($part)) {
                continue;
            }

            $token = ReservationStatus::canonicalFilter((string) $part);
            if ($token === '') {
                continue;
            }

            $canonical[] = $token;
        }

        return $canonical === [] ? null : array_values(array_unique($canonical));
    }
}

<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'preset' => ['sometimes', 'string', 'in:today,this_week,this_month,last_month'],
        ];
    }

    public function from(): string
    {
        return $this->range()[0];
    }

    public function to(): string
    {
        return $this->range()[1];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(): array
    {
        if ($this->filled('from') && $this->filled('to')) {
            return [$this->input('from'), $this->input('to')];
        }

        $today = Carbon::today();

        return match ($this->input('preset', 'this_month')) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'this_week' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };
    }
}

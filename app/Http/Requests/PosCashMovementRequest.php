<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        if (! is_array($lines) || $lines === []) {
            if ($this->filled('reason') || $this->filled('amount')) {
                $this->merge([
                    'lines' => [[
                        'reason' => $this->input('reason'),
                        'amount' => $this->input('amount'),
                    ]],
                ]);
            }

            return;
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $reason = trim((string) ($line['reason'] ?? ''));
            $amount = $line['amount'] ?? null;
            if ($reason === '' && ($amount === null || $amount === '')) {
                continue;
            }
            $normalized[] = [
                'reason' => $reason,
                'amount' => $amount,
            ];
        }
        $this->merge(['lines' => $normalized]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.reason' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => __('Add at least one line.'),
            'lines.min' => __('Add at least one line.'),
            'lines.*.reason.required' => __('Description is required.'),
            'lines.*.amount.gt' => __('Amount must be greater than zero.'),
        ];
    }
}

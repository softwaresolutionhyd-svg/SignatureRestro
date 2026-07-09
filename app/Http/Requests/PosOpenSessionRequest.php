<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosOpenSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

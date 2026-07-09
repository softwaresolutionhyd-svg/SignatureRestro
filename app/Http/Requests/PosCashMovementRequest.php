<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCashMovementRequest extends FormRequest
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
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

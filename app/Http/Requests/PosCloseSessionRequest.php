<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCloseSessionRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

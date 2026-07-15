<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosCloseSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->bypassesModulePermissions() || $user->canAccessPosClosing());
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
            'counted_cash' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMoveStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'department_id' => ['required', 'integer', 'exists:tenant.inventory_departments,id'],
            'type' => ['required', 'in:in,out,adjust,wastage'],
            'qty_uom' => [
                'required',
                'numeric',
                Rule::when($this->input('type') === 'adjust', 'min:0', 'min:0.001'),
            ],
            'uom' => ['required', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'required_if:type,wastage', 'string', 'max:255'],
        ];
    }
}

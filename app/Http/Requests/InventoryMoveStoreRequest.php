<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryMoveStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isAdjust = $this->input('type') === 'adjust';

        return [
            'department_id' => ['required', 'integer', 'exists:tenant.inventory_departments,id'],
            'type' => ['required', 'in:in,out,adjust,wastage'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'required_if:type,wastage', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
            'lines.*.qty_uom' => [
                'required',
                'numeric',
                Rule::when($isAdjust, 'min:0', 'min:0.001'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Kam az kam aik product line add karein.',
            'lines.min' => 'Kam az kam aik product line add karein.',
        ];
    }
}

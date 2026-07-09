<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderStoreRequest extends FormRequest
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
            'vendor_id' => ['required', 'integer', 'exists:tenant.purchase_vendors,id'],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'purchase_type' => ['required', 'in:debit,credit'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.uom' => ['required', 'string', 'max:30'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_percent' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        $payments = $this->input('payments');

        $this->merge([
            'items' => is_string($items) ? (json_decode($items, true) ?: []) : ($items ?? []),
            'payments' => is_string($payments) ? (json_decode($payments, true) ?: []) : ($payments ?? []),
        ]);

        foreach (['cash_tendered', 'cash_change', 'bill_tax_percent', 'bill_discount_percent', 'client_grand_total', 'client_subtotal', 'client_discount_total', 'client_tax_total'] as $key) {
            $v = $this->input($key);
            if ($v !== null && $v !== '' && is_string($v)) {
                $this->merge([$key => str_replace([',', ' '], '', $v)]);
            }
        }

        $kitchenVoids = $this->input('kitchen_voids');
        $this->merge([
            'kitchen_voids' => is_string($kitchenVoids) ? (json_decode($kitchenVoids, true) ?: []) : ($kitchenVoids ?? []),
        ]);

        $payList = $this->input('payments');
        if (is_array($payList)) {
            foreach ($payList as $i => $p) {
                if (!is_array($p)) {
                    continue;
                }
                $amt = $p['amount'] ?? null;
                if (is_string($amt)) {
                    $payList[$i]['amount'] = str_replace([',', ' '], '', $amt);
                }
            }
            $this->merge(['payments' => $payList]);
        }
    }

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
        $isHold = $this->routeIs('restaurant-pos.hold');
        $isRestaurant = $this->routeIs('restaurant-pos.checkout') || $this->routeIs('restaurant-pos.hold');
        $isCredit = $this->boolean('is_credit');
        $tablesEnabled = (string) \App\Models\Setting::get('pos_enable_tables', '1') !== '0';

        $paymentsRule = $isHold
            ? ['required', 'array', 'min:1']
            : ($isCredit ? ['nullable', 'array'] : ['required', 'array', 'min:1']);

        $payMethodRule = $isHold
            ? ['required', 'in:cash,card,bank']
            : ($isCredit ? ['nullable', 'in:cash,card,bank'] : ['required', 'in:cash,card,bank']);

        // min:0 allows Rs.0 totals; controller still requires payment sum = grand total for non-credit checkout.
        $payAmountRule = $isHold
            ? ['required', 'numeric', 'min:0']
            : ($isCredit ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0']);

        if ($isRestaurant) {
            return [
                'type' => ['required', 'in:sale,refund'],
                'service_type' => ['required', 'in:dine_in,takeaway,delivery'],
                'customer_type' => ['nullable', 'in:mess_use,booking,ast_offr'],
                'sale_mode' => ['nullable', 'in:customer,staff'],
                'staff_include_gas' => ['nullable', 'boolean'],
                'is_credit' => ['nullable', 'boolean'],
                'contact_id' => [
                    Rule::requiredIf(fn () => $this->boolean('is_credit') && $this->routeIs('restaurant-pos.checkout')),
                    'nullable',
                    'integer',
                    'exists:tenant.contacts,id',
                ],
                'guest_name' => [
                    Rule::requiredIf(fn () => $this->input('service_type') === 'delivery'
                        || ($this->input('service_type') === 'dine_in' && ! $tablesEnabled)),
                    'nullable',
                    'string',
                    'max:120',
                ],
                'room_no' => [
                    Rule::requiredIf(fn () => $this->input('service_type') === 'delivery'),
                    'nullable',
                    'string',
                    'max:50',
                ],
                'waiter_name' => ['nullable', 'string', 'max:120'],
                'order_notes' => [
                    Rule::requiredIf(fn () => $this->input('service_type') === 'delivery'),
                    'nullable',
                    'string',
                    'max:1000',
                ],
                'serve_date' => ['nullable', 'date_format:Y-m-d'],
                'serve_time' => ['nullable', 'date_format:H:i'],
                'refund_of_order_id' => ['nullable', 'integer', 'exists:tenant.pos_orders,id'],
                'resume_order_id' => ['nullable', 'integer', 'exists:tenant.pos_orders,id'],
                'table_id' => [
                    Rule::requiredIf(fn () => $this->input('service_type') === 'dine_in' && $tablesEnabled),
                    'nullable',
                    'integer',
                    'exists:tenant.pos_tables,id',
                ],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
                'items.*.uom' => ['required', 'string', 'max:30'],
                'items.*.qty' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'items.*.notes' => ['nullable', 'string', 'max:255'],
                'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
                'bill_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'bill_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'is_owner_discount' => ['nullable', 'boolean'],
                'client_grand_total' => ['nullable', 'numeric', 'min:0'],
                'client_subtotal' => ['nullable', 'numeric', 'min:0'],
                'client_discount_total' => ['nullable', 'numeric', 'min:0'],
                'client_tax_total' => ['nullable', 'numeric', 'min:0'],
                'payments' => $paymentsRule,
                'payments.*.method' => $payMethodRule,
                'payments.*.amount' => $payAmountRule,
                'payments.*.reference' => ['nullable', 'string', 'max:100'],
                'cash_tendered' => ['nullable', 'numeric', 'min:0'],
                'cash_change' => ['nullable', 'numeric', 'min:0'],
                'kitchen_voids' => ['nullable', 'array'],
                'send_to_kitchen' => ['nullable', 'boolean'],
                'kitchen_voids.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
                'kitchen_voids.*.uom' => ['required', 'string', 'max:30'],
                'kitchen_voids.*.qty' => ['required', 'numeric', 'gt:0'],
                'kitchen_voids.*.reason' => ['required', 'string', 'min:3', 'max:500'],
            ];
        }

        return [
            'type' => ['required', 'in:sale,refund'],
            'customer_type' => ['required', 'in:mess_use,booking,ast_offr'],
            'sale_mode' => ['nullable', 'in:customer,staff'],
            'staff_include_gas' => ['nullable', 'boolean'],
            'is_credit' => ['nullable', 'boolean'],
            'contact_id' => [
                Rule::requiredIf(fn () => $this->boolean('is_credit') || ($this->input('customer_type') === 'ast_offr' && ! $this->routeIs('restaurant-pos.hold'))),
                'nullable',
                'integer',
                'exists:tenant.contacts,id',
            ],
            'guest_name' => [Rule::requiredIf(fn () => $this->input('customer_type') === 'mess_use' || ($this->input('customer_type') === 'ast_offr' && $this->routeIs('restaurant-pos.hold'))), 'nullable', 'string', 'max:120'],
            'room_no' => [Rule::requiredIf(fn () => $this->input('customer_type') === 'booking'), 'nullable', 'string', 'max:50'],
            'waiter_name' => [Rule::requiredIf(fn () => $this->input('customer_type') === 'mess_use'), 'nullable', 'string', 'max:120'],
            'order_notes' => ['nullable', 'string', 'max:1000'],
            'serve_date' => ['nullable', 'date_format:Y-m-d'],
            'serve_time' => ['nullable', 'date_format:H:i'],
            'refund_of_order_id' => ['nullable', 'integer', 'exists:tenant.pos_orders,id'],
            'resume_order_id' => ['nullable', 'integer', 'exists:tenant.pos_orders,id'],
            'table_id' => ['nullable', 'integer', 'exists:tenant.pos_tables,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:tenant.inventory_products,id'],
            'items.*.uom' => ['required', 'string', 'max:30'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
            'bill_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bill_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'client_grand_total' => ['nullable', 'numeric', 'min:0'],
            'client_subtotal' => ['nullable', 'numeric', 'min:0'],
            'client_discount_total' => ['nullable', 'numeric', 'min:0'],
            'client_tax_total' => ['nullable', 'numeric', 'min:0'],
            'payments' => $paymentsRule,
            'payments.*.method' => $payMethodRule,
            'payments.*.amount' => $payAmountRule,
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'cash_change' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

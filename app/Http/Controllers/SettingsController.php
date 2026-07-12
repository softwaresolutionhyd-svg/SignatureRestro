<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
use App\Models\PosTable;
use App\Models\Setting;
use App\Support\ActivityLogger;
use App\Services\PublicStorageMirror;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    /** Default values shown when DB has nothing. */
    private const DEFAULTS = [
        'company_name'     => 'My Company',
        'company_tagline'  => '',
        'company_address'  => '',
        'company_phone'    => '',
        'company_email'    => '',
        'company_website'  => '',
        'company_ntn'      => '',
        'company_strn'     => '',
        'currency_symbol'  => 'Rs.',
        'currency_code'    => 'PKR',
        'currency_position'=> 'before',   // before | after
        'tax_label'        => 'GST',
        'tax_rate'         => '0',
        'fiscal_year_start'=> '01-01',    // MM-DD
        'invoice_prefix'   => 'INV-',
        'po_prefix'        => 'PO-',
        'date_format'      => 'd M Y',
        'company_logo'     => '',
        // POS
        'pos_open_receipt_after_sale' => '1',
        'pos_auto_print_receipt'      => '1',
        'pos_show_cash_movements'     => '1',
        'pos_show_held_orders'        => '1',
        'pos_show_customer_section'   => '1',
        'pos_show_hold_button'        => '1',
        'pos_hold_only'               => '0',
        'pos_show_refund_toggle'      => '1',
        'pos_show_discount'           => '1',
        'pos_allow_bill_print'        => '1',
        'pos_enable_tables'           => '1',
        'pos_tax_mode'                => 'line', // off | line | bill
        'pos_service_charge_enabled'  => '0',
        'pos_service_charge_percent'  => '0',
        'pos_receipt_footer_note'     => '',
        // Modules — list sizes & behaviour
        'inventory_products_per_page'       => '20',
        'inventory_moves_per_page'          => '25',
        'inventory_categories_per_page'     => '20',
        'inventory_show_low_stock_banner'   => '1',
        'purchase_orders_per_page'          => '20',
        'purchase_vendors_per_page'         => '20',
        'manufacturing_boms_per_page'       => '20',
        'manufacturing_orders_per_page'     => '25',
        'expenses_per_page'                 => '25',
        'expenses_categories_per_page'      => '20',
        'expenses_require_receipt_on_submit'=> '0',
        'accounts_per_page'                 => '25',
        'accounts_journal_per_page'         => '25',
        'accounts_auto_journal'             => '1',
        'employees_per_page'                => '20',
        'employees_ref_per_page'            => '20',
        'hr_leave_per_page'                 => '20',
        'hr_annual_leave_days'              => '14',
        'product_extra_cost_fields'         => '[{"key":"gas_charges","label":"Gas charges","rate":20,"operator":"plus","base":"cost","target":"effective_cost"}]',
    ];

    public function index()
    {
        $this->ensurePosTablesSchema();
        $settings = array_merge(self::DEFAULTS, Setting::all_map());
        $posTables = PosTable::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('settings.index', compact('settings', 'posTables'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name'      => ['required', 'string', 'max:150'],
            'company_tagline'   => ['nullable', 'string', 'max:200'],
            'company_address'   => ['nullable', 'string', 'max:500'],
            'company_phone'     => ['nullable', 'string', 'max:60'],
            'company_email'     => ['nullable', 'email', 'max:200'],
            'company_website'   => ['nullable', 'string', 'max:200'],
            'company_ntn'       => ['nullable', 'string', 'max:60'],
            'company_strn'      => ['nullable', 'string', 'max:60'],
            'currency_symbol'   => ['required', 'string', 'max:10'],
            'currency_code'     => ['required', 'string', 'max:10'],
            'currency_position' => ['required', 'in:before,after'],
            'tax_label'         => ['required', 'string', 'max:20'],
            'tax_rate'          => ['required', 'numeric', 'min:0', 'max:100'],
            'fiscal_year_start' => ['required', 'string', 'max:5'],
            'invoice_prefix'    => ['required', 'string', 'max:20'],
            'po_prefix'         => ['required', 'string', 'max:20'],
            'date_format'       => ['required', 'string', 'max:20'],
            'company_logo'      => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:1024'],
            'pos_receipt_footer_note' => ['nullable', 'string', 'max:240'],
            'pos_tax_mode' => ['required', 'in:off,line,bill'],
            'pos_service_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'inventory_products_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'inventory_moves_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'inventory_categories_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'purchase_orders_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'purchase_vendors_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'manufacturing_boms_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'manufacturing_orders_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'expenses_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'expenses_categories_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'accounts_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'accounts_journal_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'accounts_auto_journal' => ['nullable', 'boolean'],
            'employees_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'employees_ref_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'hr_leave_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'hr_annual_leave_days' => ['required', 'integer', 'min:0', 'max:365'],
            'product_extra_cost_fields' => ['nullable', 'array', 'max:20'],
            'product_extra_cost_fields.*.label' => ['nullable', 'string', 'max:60'],
            'product_extra_cost_fields.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'product_extra_cost_fields.*.operator' => ['nullable', 'in:plus,minus,multiply,divide'],
            'product_extra_cost_fields.*.calculate_to' => ['nullable', 'in:effective_cost,price'],
            'product_extra_cost_fields.*.base_index' => ['nullable', 'integer', 'min:-3', 'max:19'],
        ]);

        $this->validateProductExtraCostFieldReferences($request->input('product_extra_cost_fields', []));

        foreach ([
            'pos_open_receipt_after_sale',
            'pos_auto_print_receipt',
            'pos_show_cash_movements',
            'pos_show_held_orders',
            'pos_show_customer_section',
            'pos_show_hold_button',
            'pos_hold_only',
            'pos_show_refund_toggle',
            'pos_show_discount',
            'pos_allow_bill_print',
            'pos_enable_tables',
            'pos_service_charge_enabled',
            'inventory_show_low_stock_banner',
            'expenses_require_receipt_on_submit',
            'accounts_auto_journal',
        ] as $boolKey) {
            $data[$boolKey] = $request->boolean($boolKey) ? '1' : '0';
        }

        // Handle logo upload
        if ($request->hasFile('company_logo')) {
            $old = Setting::get('company_logo');
            if ($old && Storage::disk('public')->exists($old)) {
                PublicStorageMirror::unpublish($old);
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('company_logo')->store('logos', 'public');
            PublicStorageMirror::publish($path);
            $data['company_logo'] = $path;
        } else {
            unset($data['company_logo']);
        }

        $data['product_extra_cost_fields'] = json_encode(
            $this->normalizeProductExtraCostFields($request->input('product_extra_cost_fields', []))
        ) ?: '[]';

        Setting::setMany($data);

        ActivityLogger::log('settings.updated', 'Company / app settings updated');

        return redirect()->route('settings.index')->with('status', 'Settings saved successfully.');
    }

    public function storePosTable(Request $request): RedirectResponse
    {
        $this->ensurePosTablesSchema();
        abort_unless((string) Setting::get('pos_enable_tables', '1') !== '0', 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        PosTable::query()->firstOrCreate([
            'name' => trim($data['name']),
        ], [
            'active' => true,
        ]);

        return redirect()->route('settings.index', ['tab' => 'pos'])->with('status', 'Table added.');
    }

    public function destroyPosTable(PosTable $posTable): RedirectResponse
    {
        $this->ensurePosTablesSchema();
        abort_unless((string) Setting::get('pos_enable_tables', '1') !== '0', 403);

        $inUse = PosOrder::query()
            ->where('table_id', $posTable->id)
            ->whereIn('status', ['draft', 'paid'])
            ->exists();

        if ($inUse) {
            return redirect()->route('settings.index', ['tab' => 'pos'])
                ->withErrors(['pos_table' => 'Table cannot be deleted because orders exist for it.']);
        }

        $posTable->delete();

        return redirect()->route('settings.index', ['tab' => 'pos'])->with('status', 'Table deleted.');
    }

    private function ensurePosTablesSchema(): void
    {
        try {
            if (! Schema::hasTable('pos_tables')) {
                Schema::create('pos_tables', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 60)->unique();
                    $table->boolean('active')->default(true);
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  mixed  $rows
     * @return list<array{key:string,label:string,rate:float,operator:string,base:string,target:string}>
     */
    private function normalizeProductExtraCostFields(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $rows = array_values($rows);
        $keysByFormIndex = [];
        $out = [];
        $usedKeys = [];
        foreach ($rows as $i => $row) {
            $label = trim((string) data_get($row, 'label', ''));
            if ($label === '') {
                $keysByFormIndex[$i] = null;

                continue;
            }

            $baseFormIdx = (int) data_get($row, 'base_index', -1);
            if ($baseFormIdx < -3) {
                $baseFormIdx = -1;
            }
            if ($baseFormIdx >= $i) {
                $baseFormIdx = -1;
            }
            $base = match ($baseFormIdx) {
                -2 => 'effective_cost',
                -3 => 'price',
                default => 'cost',
            };
            if ($baseFormIdx >= 0) {
                $refKey = $keysByFormIndex[$baseFormIdx] ?? null;
                $base = is_string($refKey) && $refKey !== '' ? $refKey : 'cost';
            }

            $rate = (float) data_get($row, 'rate', 0);
            $operator = (string) data_get($row, 'operator', 'plus');
            if (! in_array($operator, ['plus', 'minus', 'multiply', 'divide'], true)) {
                $operator = 'plus';
            }
            $target = (string) data_get($row, 'calculate_to', data_get($row, 'target', 'effective_cost'));
            if (! in_array($target, ['effective_cost', 'price'], true)) {
                $target = 'effective_cost';
            }
            if ($operator === 'divide' && $rate <= 0) {
                $rate = 1;
            }
            if (in_array($operator, ['plus', 'minus'], true) && $rate > 500) {
                $rate = 500;
            }
            $baseKey = strtolower((string) preg_replace('/[^a-z0-9]+/', '_', $label));
            $baseKey = trim($baseKey, '_');
            if ($baseKey === '') {
                $baseKey = 'cost_field';
            }

            $key = $baseKey;
            $suffix = 2;
            while (isset($usedKeys[$key])) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $usedKeys[$key] = true;
            $keysByFormIndex[$i] = $key;
            $out[] = [
                'key' => $key,
                'label' => $label,
                'rate' => round(max($rate, 0), 6),
                'operator' => $operator,
                'base' => $base,
                'target' => $target,
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $rows
     */
    private function validateProductExtraCostFieldReferences(mixed $rows): void
    {
        if (! is_array($rows)) {
            return;
        }
        $rows = array_values($rows);
        foreach ($rows as $i => $row) {
            $label = trim((string) data_get($row, 'label', ''));
            if ($label === '') {
                continue;
            }
            $operator = (string) data_get($row, 'operator', 'plus');
            $rate = (float) data_get($row, 'rate', 0);
            if (in_array($operator, ['plus', 'minus'], true) && $rate > 500) {
                throw ValidationException::withMessages([
                    "product_extra_cost_fields.{$i}.rate" => ['For add/subtract, rate cannot exceed 500%.'],
                ]);
            }
            if ($operator === 'divide' && $rate <= 0) {
                throw ValidationException::withMessages([
                    "product_extra_cost_fields.{$i}.rate" => ['For divide, divisor (rate) must be greater than 0.'],
                ]);
            }
            $bi = (int) data_get($row, 'base_index', -1);
            if ($bi >= $i) {
                throw ValidationException::withMessages([
                    "product_extra_cost_fields.{$i}.base_index" => ['Base must be Cost, Effective Cost, Sale Price, or a row above this one.'],
                ]);
            }
            if ($bi >= 0 && trim((string) data_get($rows[$bi] ?? [], 'label', '')) === '') {
                throw ValidationException::withMessages([
                    "product_extra_cost_fields.{$i}.base_index" => ['The chosen base row must have a label.'],
                ]);
            }
        }
    }
}

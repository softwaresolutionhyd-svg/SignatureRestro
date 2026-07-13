<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDepartment;
use App\Models\Setting;
use App\Services\InventoryStockService;
use App\Support\EnsuresKitchenAgentSchema;
use Illuminate\Http\Request;

class KitchenAgentController extends Controller
{
    use EnsuresKitchenAgentSchema;

    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    public function index()
    {
        $this->ensureKitchenAgentSchema();
        $this->stockService->ensureWarehouse();

        $departments = InventoryDepartment::query()
            ->where('active', true)
            ->orderByDesc('is_warehouse')
            ->orderBy('name')
            ->get();

        $cashier = [
            'printer_ip'   => Setting::get('cashier_printer_ip', ''),
            'printer_port' => Setting::get('cashier_printer_port', ''),
            'printer_name' => Setting::get('cashier_printer_name', ''),
        ];

        return view('inventory.kitchen-agents.index', compact('departments', 'cashier'));
    }

    public function update(Request $request)
    {
        $this->ensureKitchenAgentSchema();

        $data = $request->validate([
            'printers'                 => ['array'],
            'printers.*.printer_ip'    => ['nullable', 'ip'],
            'printers.*.printer_port'  => ['nullable', 'integer', 'min:1', 'max:65535'],
            'printers.*.printer_name'  => ['nullable', 'string', 'max:100'],

            'cashier_printer_ip'       => ['nullable', 'ip'],
            'cashier_printer_port'     => ['nullable', 'integer', 'min:1', 'max:65535'],
            'cashier_printer_name'     => ['nullable', 'string', 'max:100'],
        ], [
            'printers.*.printer_ip.ip' => 'Valid IP address likhein (jaise 192.168.1.50).',
            'cashier_printer_ip.ip'    => 'Cashier ke liye valid IP likhein (jaise 192.168.1.60).',
        ]);

        $printers = $data['printers'] ?? [];

        foreach ($printers as $departmentId => $row) {
            $department = InventoryDepartment::find((int) $departmentId);
            if (! $department) {
                continue;
            }

            $ip = trim((string) ($row['printer_ip'] ?? ''));
            $port = $row['printer_port'] ?? null;
            $name = trim((string) ($row['printer_name'] ?? ''));

            $department->update([
                'printer_ip'   => $ip !== '' ? $ip : null,
                'printer_port' => $ip !== '' ? ((int) ($port ?: 9100)) : null,
                'printer_name' => $name !== '' ? $name : null,
            ]);
        }

        $cashierIp = trim((string) ($data['cashier_printer_ip'] ?? ''));
        Setting::set('cashier_printer_ip', $cashierIp !== '' ? $cashierIp : '');
        Setting::set('cashier_printer_port', $cashierIp !== '' ? ((string) ($data['cashier_printer_port'] ?? 9100 ?: 9100)) : '');
        Setting::set('cashier_printer_name', trim((string) ($data['cashier_printer_name'] ?? '')));

        return redirect()
            ->route('inventory.kitchen-agents.index')
            ->with('status', 'Kitchen agents (printer IPs) save ho gaye.');
    }
}

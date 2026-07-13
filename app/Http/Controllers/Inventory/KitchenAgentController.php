<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryDepartment;
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

        return view('inventory.kitchen-agents.index', compact('departments'));
    }

    public function update(Request $request)
    {
        $this->ensureKitchenAgentSchema();

        $data = $request->validate([
            'printers'                 => ['array'],
            'printers.*.printer_ip'    => ['nullable', 'ip'],
            'printers.*.printer_port'  => ['nullable', 'integer', 'min:1', 'max:65535'],
            'printers.*.printer_name'  => ['nullable', 'string', 'max:100'],
        ], [
            'printers.*.printer_ip.ip' => 'Valid IP address likhein (jaise 192.168.1.50).',
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

        return redirect()
            ->route('inventory.kitchen-agents.index')
            ->with('status', 'Kitchen agents (printer IPs) save ho gaye.');
    }
}

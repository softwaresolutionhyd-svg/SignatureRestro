@extends('layouts.admin')

@section('title', 'Kitchen Agents - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Kitchen Agents')

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-printer me-1"></i>
        Har department ko us ka <strong>printer IP</strong> assign karein (jo current router / LAN par connected hain).
        Port khaali chhorne par default <strong>9100</strong> use hoga (network / ESC-POS printers).
    </div>

    <form method="POST" action="{{ route('inventory.kitchen-agents.update') }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold"><i class="bi bi-hdd-network me-1"></i> Kitchen Agents — Department Printers</div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i> Save
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width:180px;">Department</th>
                        <th style="min-width:180px;">Printer IP</th>
                        <th style="min-width:120px;">Port</th>
                        <th style="min-width:180px;">Printer name <span class="text-secondary fw-normal small">(optional)</span></th>
                        <th class="text-center" style="width:90px;">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($departments as $d)
                        <tr>
                            <td class="fw-semibold">
                                {{ $d->name }}
                                @if($d->is_warehouse)
                                    <span class="badge text-bg-warning ms-1">Warehouse</span>
                                @endif
                            </td>
                            <td>
                                <input type="text"
                                       name="printers[{{ $d->id }}][printer_ip]"
                                       value="{{ old('printers.'.$d->id.'.printer_ip', $d->printer_ip) }}"
                                       class="form-control form-control-sm @error('printers.'.$d->id.'.printer_ip') is-invalid @enderror"
                                       placeholder="192.168.1.50"
                                       inputmode="numeric" autocomplete="off">
                                @error('printers.'.$d->id.'.printer_ip')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="number"
                                       name="printers[{{ $d->id }}][printer_port]"
                                       value="{{ old('printers.'.$d->id.'.printer_port', $d->printer_port) }}"
                                       class="form-control form-control-sm @error('printers.'.$d->id.'.printer_port') is-invalid @enderror"
                                       placeholder="9100" min="1" max="65535">
                                @error('printers.'.$d->id.'.printer_port')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </td>
                            <td>
                                <input type="text"
                                       name="printers[{{ $d->id }}][printer_name]"
                                       value="{{ old('printers.'.$d->id.'.printer_name', $d->printer_name) }}"
                                       class="form-control form-control-sm"
                                       placeholder="e.g. Kitchen Epson" maxlength="100" autocomplete="off">
                            </td>
                            <td class="text-center">
                                @if($d->printer_ip)
                                    <span class="badge text-bg-success">Assigned</span>
                                @else
                                    <span class="badge text-bg-secondary">Not set</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No active departments.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i> Save
                </button>
            </div>
        </div>
    </form>
@endsection

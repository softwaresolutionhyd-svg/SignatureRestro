@php $rpCompany = \App\Models\Setting::get('company_name', config('app.name')); @endphp
<div class="print-only report-print-header" style="display:none;">
    <div style="text-align:center;">
        <div style="font-size:16pt;font-weight:bold;">{{ $rpCompany }}</div>
        <div style="font-size:12pt;margin-top:2px;">{{ $reportName ?? 'Report' }}</div>
        <div style="font-size:10pt;margin-top:4px;">
            @if(!empty($period)){{ $period }} &nbsp;|&nbsp; @endif Printed: {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>
    <hr style="border:0;border-top:1px solid #000;margin:10px 0 14px;">
</div>

<?php

namespace App\Http\Controllers;

use App\Models\CustomFormReport;
use App\Models\CustomFormTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CustomFormReportsController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureTables();

        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));

        $templates = CustomFormTemplate::query()->orderBy('name')->get();
        $reports = CustomFormReport::query()
            ->with('template:id,name')
            ->where('month', $month)
            ->where('year', $year)
            ->latest('id')
            ->get();

        return view('custom_forms.index', compact('month', 'year', 'templates', 'reports'));
    }

    public function storeTemplate(Request $request)
    {
        $this->ensureTables();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'heading' => ['required', 'string', 'max:200'],
            'show_remarks' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.type' => ['required', 'in:section,item,total'],
            'rows.*.serial' => ['nullable', 'string', 'max:20'],
            'rows.*.label' => ['required', 'string', 'max:200'],
        ]);

        $rows = collect((array) $data['rows'])->values()->map(function ($r, $idx) {
            $label = trim((string) ($r['label'] ?? ''));
            $key = 'r'.($idx + 1).'_'.strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $label));
            $key = trim($key, '_');

            return [
                'type' => (string) ($r['type'] ?? 'item'),
                'serial' => trim((string) ($r['serial'] ?? '')),
                'label' => $label,
                'key' => $key,
            ];
        })->all();

        CustomFormTemplate::query()->create([
            'name' => trim((string) $data['name']),
            'heading' => trim((string) $data['heading']),
            'rows_json' => $rows,
            'show_remarks' => $request->boolean('show_remarks', true),
            'active' => true,
        ]);

        return redirect()->route('custom-forms.index')->with('status', 'Template created.');
    }

    public function editTemplate(CustomFormTemplate $template)
    {
        $this->ensureTables();
        return view('custom_forms.template_edit', compact('template'));
    }

    public function updateTemplate(Request $request, CustomFormTemplate $template)
    {
        $this->ensureTables();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'heading' => ['required', 'string', 'max:200'],
            'show_remarks' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.type' => ['required', 'in:section,item,total'],
            'rows.*.serial' => ['nullable', 'string', 'max:20'],
            'rows.*.label' => ['required', 'string', 'max:200'],
            'active' => ['nullable', 'boolean'],
        ]);

        $rows = collect((array) $data['rows'])->values()->map(function ($r, $idx) {
            $label = trim((string) ($r['label'] ?? ''));
            $existingKey = trim((string) ($r['key'] ?? ''));
            $key = $existingKey !== '' ? $existingKey : 'r'.($idx + 1).'_'.strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $label));
            $key = trim($key, '_');

            return [
                'type' => (string) ($r['type'] ?? 'item'),
                'serial' => trim((string) ($r['serial'] ?? '')),
                'label' => $label,
                'key' => $key,
            ];
        })->all();

        $template->update([
            'name' => trim((string) $data['name']),
            'heading' => trim((string) $data['heading']),
            'rows_json' => $rows,
            'show_remarks' => $request->boolean('show_remarks'),
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('custom-forms.index')->with('status', 'Template updated.');
    }

    public function fill(Request $request, CustomFormTemplate $template)
    {
        $this->ensureTables();
        $month = max(1, min(12, (int) $request->query('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));

        $report = CustomFormReport::query()
            ->where('template_id', $template->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return view('custom_forms.fill', compact('template', 'month', 'year', 'report'));
    }

    public function saveFill(Request $request, CustomFormTemplate $template)
    {
        $this->ensureTables();
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'values' => ['nullable', 'array'],
        ]);

        $rows = (array) ($template->rows_json ?? []);
        $values = [];
        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $v = data_get($data, 'values.'.$key, []);
            $values[$key] = [
                'amount' => (string) ($v['amount'] ?? ''),
                'remarks' => (string) ($v['remarks'] ?? ''),
                'flag' => (string) ($v['flag'] ?? ''),
            ];
        }

        CustomFormReport::query()->updateOrCreate(
            [
                'template_id' => $template->id,
                'month' => (int) $data['month'],
                'year' => (int) $data['year'],
            ],
            [
                'values_json' => $values,
                'saved_by' => $request->user()?->id,
            ]
        );

        return redirect()->route('custom-forms.fill', ['template' => $template, 'month' => $data['month'], 'year' => $data['year']])
            ->with('status', 'Report saved.');
    }

    public function showReport(CustomFormReport $report)
    {
        $this->ensureTables();
        $report->load('template');
        return view('custom_forms.show', compact('report'));
    }

    public function destroyTemplate(CustomFormTemplate $template)
    {
        $this->ensureTables();
        $template->delete();

        return redirect()->route('custom-forms.index')->with('status', 'Template deleted.');
    }

    public function destroyReport(CustomFormReport $report)
    {
        $this->ensureTables();
        $report->delete();

        return redirect()->route('custom-forms.index')->with('status', 'Report deleted.');
    }

    private function ensureTables(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('custom_form_templates')) {
            $schema->create('custom_form_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('heading', 200);
                $table->json('rows_json')->nullable();
                $table->boolean('show_remarks')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        } else {
            $schema->table('custom_form_templates', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('custom_form_templates', 'show_remarks')) {
                    $table->boolean('show_remarks')->default(true)->after('rows_json');
                }
            });
        }
        if (! $schema->hasTable('custom_form_reports')) {
            $schema->create('custom_form_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('template_id')->constrained('custom_form_templates')->cascadeOnDelete();
                $table->unsignedTinyInteger('month');
                $table->unsignedSmallInteger('year');
                $table->json('values_json')->nullable();
                $table->unsignedBigInteger('saved_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'template_id', 'month', 'year'], 'cfr_company_template_period_unique');
            });
        }
    }
}


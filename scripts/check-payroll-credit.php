<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$e = App\Models\Employee::query()->where('name', 'like', '%RANA SHAHID%')->first();
echo 'employee: '.($e?->id). ' contact_id: '.($e?->contact_id).PHP_EOL;

$p = App\Models\PayrollEntry::query()->where('employee_id', $e?->id)->orderByDesc('period')->first();
echo 'payroll period: '.($p?->period).' food_bill: '.($p?->food_bill).' status: '.($p?->status).PHP_EOL;

$c = App\Models\Contact::query()->where('name', 'like', '%RANA SHAHID%')->first();
echo 'contact: '.($c?->id).' balance: '.($c?->balance ?? 0).PHP_EOL;

echo 'credit entries: '.App\Models\CreditLedger::query()->where('contact_id', $c?->id)->count().PHP_EOL;
echo 'payment entries: '.App\Models\CreditLedger::query()->where('contact_id', $c?->id)->where('type', 'payment')->count().PHP_EOL;
echo 'payroll_entry_id col: '.(Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('credit_ledger', 'payroll_entry_id') ? 'yes' : 'no').PHP_EOL;

echo 'tenant users: '.Illuminate\Support\Facades\DB::connection('tenant')->table('users')->count().PHP_EOL;
echo 'mysql users: '.Illuminate\Support\Facades\DB::connection('mysql')->table('users')->count().PHP_EOL;
echo 'tenant user ids: '.Illuminate\Support\Facades\DB::connection('tenant')->table('users')->pluck('id')->join(',').PHP_EOL;

$service = app(App\Services\PayrollFoodBillSettlementService::class);
if ($p) {
    $service->settle($p, (int) ($p->created_by ?: 0));
    echo 'settle ran for payroll '.$p->id.PHP_EOL;
}

$c?->refresh();
echo 'after balance: '.($c?->balance ?? 0).PHP_EOL;
echo 'payment entries: '.App\Models\CreditLedger::query()->where('contact_id', $c?->id)->where('type', 'payment')->count().PHP_EOL;

<?php

namespace App\Services\Sync;

use App\Models\Contact;
use App\Models\CreditLedger;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPayment;
use App\Models\EmployeeAttendance;
use App\Models\PayrollEntry;

class SyncPayrollQueueService
{
    public function __construct(
        private readonly SyncOutboxRecorder $recorder,
        private readonly CloudSyncService $sync,
    ) {}

    public function queuePayrollData(?string $period = null): int
    {
        if (! $this->sync->isLocalRole()) {
            return 0;
        }

        $queued = 0;

        EmployeeLoan::withoutGlobalScope('company')
            ->orderBy('id')
            ->each(function (EmployeeLoan $row) use (&$queued) {
                $this->recorder->recordModel($row, 'upsert');
                $queued++;
            });

        EmployeeLoanPayment::withoutGlobalScope('company')
            ->orderBy('id')
            ->each(function (EmployeeLoanPayment $row) use (&$queued) {
                $this->recorder->recordModel($row, 'upsert');
                $queued++;
            });

        $payrollQuery = PayrollEntry::withoutGlobalScope('company')->orderBy('id');
        if ($period !== null && $period !== '') {
            $payrollQuery->where('period', $period);
        }
        $payrollQuery->each(function (PayrollEntry $row) use (&$queued) {
            $this->recorder->recordModel($row, 'upsert');
            $queued++;
        });

        Employee::withoutGlobalScope('company')
            ->whereNotNull('contact_id')
            ->orderBy('id')
            ->each(function (Employee $row) use (&$queued) {
                $this->recorder->recordModel($row, 'upsert');
                $queued++;
            });

        Contact::withoutGlobalScope('company')
            ->where('category', 'mess_bill')
            ->orderBy('id')
            ->each(function (Contact $row) use (&$queued) {
                $this->recorder->recordModel($row, 'upsert');
                $queued++;
            });

        if ($period !== null && $period !== '') {
            [$start, $end] = app(\App\Services\PayrollSalaryService::class)->periodBounds($period);

            CreditLedger::withoutGlobalScope('company')
                ->whereBetween('entry_date', [$start, $end])
                ->orderBy('id')
                ->each(function (CreditLedger $row) use (&$queued) {
                    $this->recorder->recordModel($row, 'upsert');
                    $queued++;
                });

            EmployeeAttendance::withoutGlobalScope('company')
                ->whereBetween('attendance_date', [$start, $end])
                ->orderBy('id')
                ->each(function (EmployeeAttendance $row) use (&$queued) {
                    $this->recorder->recordModel($row, 'upsert');
                    $queued++;
                });
        }

        app(SyncPushScheduler::class)->schedule();

        return $queued;
    }
}

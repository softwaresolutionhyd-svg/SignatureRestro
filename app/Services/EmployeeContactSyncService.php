<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Employee;
use App\Support\EnsuresPayrollSchema;

class EmployeeContactSyncService
{
    use EnsuresPayrollSchema;

    public function ensureContactForEmployee(Employee $employee): ?Contact
    {
        $this->ensurePayrollSchema();

        $employee->loadMissing('contact');

        if ($employee->contact_id && $employee->contact) {
            $this->updateContactFromEmployee($employee->contact, $employee);

            return $employee->contact;
        }

        $contact = null;
        if ($employee->phone) {
            $contact = Contact::query()
                ->where('name', $employee->name)
                ->where('phone', $employee->phone)
                ->first();
        }
        if ($contact === null) {
            $contact = Contact::query()
                ->where('name', $employee->name)
                ->first();
        }

        if ($contact === null) {
            $contact = Contact::create([
                'name' => $employee->name,
                'phone' => $employee->phone,
                'email' => $employee->email,
                'category' => 'mess_bill',
                'active' => (bool) $employee->active,
                'notes' => 'Employee '.$employee->employee_no,
            ]);
        } else {
            $this->updateContactFromEmployee($contact, $employee);
        }

        if ((int) $employee->contact_id !== (int) $contact->id) {
            $employee->forceFill(['contact_id' => $contact->id])->save();
        }

        return $contact;
    }

    public function syncAllEmployees(): int
    {
        $count = 0;
        Employee::query()->orderBy('id')->chunk(100, function ($employees) use (&$count) {
            foreach ($employees as $employee) {
                $this->ensureContactForEmployee($employee);
                $count++;
            }
        });

        return $count;
    }

    private function updateContactFromEmployee(Contact $contact, Employee $employee): void
    {
        $contact->update([
            'name' => $employee->name,
            'phone' => $employee->phone ?: $contact->phone,
            'email' => $employee->email ?: $contact->email,
            'active' => (bool) $employee->active,
            'notes' => 'Employee '.$employee->employee_no,
        ]);
    }
}

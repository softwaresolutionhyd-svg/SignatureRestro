<?php

namespace App\Http\Controllers;

use App\Models\Employee;

class DashboardController extends Controller
{
    public function index()
    {
        $linkedEmployee = Employee::query()
            ->with('designation:id,name')
            ->where('user_id', auth()->id())
            ->where('active', true)
            ->first(['id', 'name', 'employee_no', 'designation_id']);

        return view('dashboard.index', compact('linkedEmployee'));
    }
}

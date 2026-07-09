<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;

class PasswordResetRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function create()
    {
        return view('auth.request-password-reset');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:200'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            if (! PasswordResetRequest::tableExists()) {
                return back()->with(
                    'status',
                    'Abhi password-reset queue database mein tayar nahi. Admin se `php artisan migrate` chalwaein.'
                );
            }
            PasswordResetRequest::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => 'pending',
                ],
                [
                    'company_id' => $user->company_id,
                ]
            );
        }

        return back()->with(
            'status',
            'Agar yeh email system mein mojood hai to admin ko request chali gayi hai. Admin reset ke baad naya password: Abcd1234'
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PasswordResetRequest extends Model
{
    protected $connection = 'mysql';

    /** True when the migration has been applied (avoids 500s before migrate). */
    public static function tableExists(): bool
    {
        try {
            return (bool) Cache::rememberForever('schema:mysql:password_reset_requests', function () {
                return Schema::connection('mysql')->hasTable('password_reset_requests');
            });
        } catch (\Throwable) {
            return Schema::connection('mysql')->hasTable('password_reset_requests');
        }
    }

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

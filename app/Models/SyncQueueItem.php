<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncQueueItem extends Model
{
    protected $table = 'sync_queue';

    protected $fillable = [
        'table_name',
        'record_key',
        'action',
        'payload',
        'attempts',
        'last_error',
        'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];
}

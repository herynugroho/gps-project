<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandLog extends Model
{
    protected $fillable = [
        'imei',
        'command',
        'reply',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'imei', 'imei');
    }
}

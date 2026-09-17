<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $primaryKey = 'log_id';

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'module', 'record_id', 'record_type', 'description', 'ip_address'];

    public static function record(?int $userId, string $action, string $module, string $description, ?int $recordId = null): void
    {
        static::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }
}

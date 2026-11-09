<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'maintenance_schedule_id', 'technician_id', 'performed_at', 'findings', 'actions_performed', 'next_maintenance_on', 'status'];

    protected function casts(): array
    {
        return ['performed_at' => 'datetime', 'next_maintenance_on' => 'date'];
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function schedule()
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}

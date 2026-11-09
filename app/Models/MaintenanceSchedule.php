<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = ['asset_id', 'assigned_to', 'title', 'instructions', 'scheduled_for', 'status'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'date'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function record()
    {
        return $this->hasOne(MaintenanceRecord::class);
    }
}

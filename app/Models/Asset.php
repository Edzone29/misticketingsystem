<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['asset_tag', 'serial_number', 'name', 'asset_category_id', 'brand', 'model', 'department_id', 'assigned_user_id', 'building', 'room_number', 'acquired_on', 'warranty_expires_on', 'condition', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'warranty_expires_on' => 'date'];
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tickets()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assets')->with(['requester', 'category', 'priority']);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class)->with('technician')->latest('performed_at');
    }

    public function maintenanceSchedules()
    {
        return $this->hasMany(MaintenanceSchedule::class)->with('technician')->latest('scheduled_for');
    }
}

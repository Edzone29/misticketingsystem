<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id', 'department_id', 'employee_number', 'first_name', 'middle_name', 'last_name',
        'name',
        'email',
        'contact_number', 'position', 'is_active', 'last_login_at', 'must_change_password',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function headedDepartments()
    {
        return $this->hasMany(Department::class, 'head_user_id');
    }

    public function requestedTickets()
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignments()
    {
        return $this->hasMany(TicketAssignment::class, 'technician_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(TicketAssignment::class, 'technician_id')->whereNull('ended_at')->whereHas('ticket', fn ($query) => $query->whereNotIn('status', ['Resolved', 'Closed']));
    }

    public function assignedTickets()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assignments', 'technician_id', 'ticket_id')->withPivot(['assigned_by', 'reason', 'assigned_at', 'ended_at'])->withTimestamps();
    }

    public function assignedTicketHistory()
    {
        return $this->hasMany(TicketAssignment::class, 'assigned_by');
    }

    public function ticketComments()
    {
        return $this->hasMany(TicketComment::class);
    }

    public function ticketAttachments()
    {
        return $this->hasMany(TicketAttachment::class, 'uploaded_by');
    }

    public function ticketStatusChanges()
    {
        return $this->hasMany(TicketStatusHistory::class, 'changed_by');
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function maintenanceAssignments()
    {
        return $this->hasMany(MaintenanceSchedule::class, 'assigned_to');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class, 'technician_id');
    }

    public function knowledgeArticles()
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'author_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isRole('system_administrator') || $this->role?->permissions()->where('name', $permission)->exists() === true;
    }

    public function canManageTickets(): bool
    {
        return $this->isRole('system_administrator', 'mis_head');
    }

    public function isMisStaff(): bool
    {
        return $this->isRole('system_administrator', 'mis_head', 'mis_technician');
    }
}

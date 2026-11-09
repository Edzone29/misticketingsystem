<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['action' => ['nullable', 'string', 'max:100'], 'user_id' => ['nullable', 'exists:users,id'], 'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);
        $logs = AuditLog::with('user')->when($request->action, fn ($q, $a) => $q->where('action', 'like', "%$a%"))->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))->when($request->start_date, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))->when($request->end_date, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))->latest('id')->paginate(30)->withQueryString();

        return view('audit.index', ['logs' => $logs, 'users' => User::orderBy('last_name')->get()]);
    }
}

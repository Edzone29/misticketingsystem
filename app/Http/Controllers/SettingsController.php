<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\SlaPolicy;
use App\Models\TicketPriority;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', ['settings' => DB::table('system_settings')->pluck('value', 'key'), 'policies' => SlaPolicy::with('priority')->orderBy('ticket_priority_id')->get(), 'holidays' => Holiday::whereDate('date', '>=', today())->orderBy('date')->limit(30)->get(), 'priorities' => TicketPriority::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Audit $audit)
    {
        $data = $request->validate(['university_name' => ['required', 'string', 'max:180'], 'system_name' => ['required', 'string', 'max:100'], 'contact_email' => ['nullable', 'email', 'max:255'], 'ticket_prefix' => ['required', 'regex:/^[A-Z][A-Z0-9-]{1,9}$/'], 'working_days' => ['required', 'array', 'min:1'], 'working_days.*' => ['required', Rule::in(['0', '1', '2', '3', '4', '5', '6'])], 'working_start' => ['required', 'date_format:H:i'], 'working_end' => ['required', 'date_format:H:i', 'after:working_start'], 'reopen_days' => ['required', 'integer', 'between:0,30'], 'sla_pause_statuses' => ['nullable', 'array'], 'sla_pause_statuses.*' => [Rule::in(['Pending User', 'On Hold'])], 'sla_reminder_minutes' => ['required', 'integer', 'between:1,1440'], 'sla_escalation_minutes' => ['required', 'integer', 'between:0,10080'], 'email_notifications' => ['nullable', 'boolean']]);
        $values = ['university_name' => $data['university_name'], 'system_name' => $data['system_name'], 'contact_email' => $data['contact_email'] ?? '', 'ticket_prefix' => $data['ticket_prefix'], 'working_days' => implode(',', array_unique($data['working_days'])), 'working_start' => $data['working_start'], 'working_end' => $data['working_end'], 'reopen_days' => (string) $data['reopen_days'], 'sla_pause_statuses' => implode(',', array_unique($data['sla_pause_statuses'] ?? [])), 'sla_reminder_minutes' => (string) $data['sla_reminder_minutes'], 'sla_escalation_minutes' => (string) $data['sla_escalation_minutes'], 'email_notifications' => ($data['email_notifications'] ?? false) ? '1' : '0'];
        foreach ($values as $key => $value) {
            DB::table('system_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now(), 'created_at' => now()]);
        }
        $audit->record('settings.updated', null, ['keys' => array_keys($values)]);

        return back()->with('success', 'System settings saved.');
    }

    public function updateSla(Request $request, Audit $audit)
    {
        $data = $request->validate(['policies' => ['required', 'array'], 'policies.*.response_minutes' => ['required', 'integer', 'between:1,10080'], 'policies.*.resolution_minutes' => ['required', 'integer', 'between:1,43200'], 'policies.*.enabled' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($data) {
            foreach ($data['policies'] as $id => $policy) {
                SlaPolicy::whereKey($id)->update(['response_minutes' => $policy['response_minutes'], 'resolution_minutes' => $policy['resolution_minutes'], 'enabled' => (bool) ($policy['enabled'] ?? false)]);
            }
        });
        $audit->record('sla_policies.updated');

        return back()->with('success', 'SLA policies saved.');
    }

    public function storeHoliday(Request $request, Audit $audit)
    {
        $data = $request->validate(['date' => ['required', 'date'], 'name' => ['required', 'string', 'max:180'], 'is_working_day' => ['nullable', 'boolean']]);
        $holiday = Holiday::updateOrCreate(['date' => $data['date']], ['name' => $data['name'], 'is_working_day' => (bool) ($data['is_working_day'] ?? false)]);
        $audit->record('holiday.saved', $holiday);

        return back()->with('success', 'Holiday calendar updated.');
    }

    public function destroyHoliday(Holiday $holiday, Audit $audit)
    {
        $audit->record('holiday.removed', $holiday, ['date' => $holiday->date->toDateString()]);
        $holiday->delete();

        return back()->with('success','Holiday removed from the working calendar.');
    }
}

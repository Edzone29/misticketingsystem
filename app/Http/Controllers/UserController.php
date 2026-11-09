<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['role', 'department'])->when($request->search, fn ($q, $s) => $q->where(fn ($b) => $b->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")))->when($request->department_id, fn ($q, $id) => $q->where('department_id', $id))->when($request->role_id, fn ($q, $id) => $q->where('role_id', $id))->orderBy('last_name')->paginate(20)->withQueryString();

        return view('users.index', ['users' => $users, 'departments' => Department::orderBy('name')->get(), 'roles' => Role::orderBy('id')->get()]);
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user' => $user,
            'departments' => Department::orderBy('name')->get(),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, Audit $audit)
    {
        $data = $this->validated($request);
        $data['name'] = trim($data['first_name'].' '.($data['middle_name'] ?? '').' '.$data['last_name']);
        $temporaryPassword = null;
        if (empty($data['email'])) {
            $temporaryPassword = $this->temporaryPassword();
            $data['password'] = $temporaryPassword;
            $data['must_change_password'] = true;
        } else {
            $data['password'] = Str::random(64);
            $data['must_change_password'] = false;
        }
        $data['is_active'] = true;
        $user = User::create($data);
        $audit->record('user.created', $user, ['role_id' => $user->role_id]);
        if ($temporaryPassword !== null) {
            return back()
                ->with('success', 'User account created without email. Give the temporary password to the user through an approved private channel; they must change it at first sign-in.')
                ->with('temporary_password', $temporaryPassword);
        }

        $deliveryMessage = $this->sendPasswordSetupLink($user);

        return back()->with('success', 'User account created. '.$deliveryMessage);
    }

    public function update(Request $request, User $user, Audit $audit)
    {
        $data = $this->validated($request, $user);
        $data['name'] = trim($data['first_name'].' '.($data['middle_name'] ?? '').' '.$data['last_name']);
        $requestedRole = Role::findOrFail($data['role_id']);
        abort_if($request->user()->is($user) && (int) $data['role_id'] !== $user->role_id, 422, 'You cannot change your own administrator role.');

        DB::transaction(function () use ($user, $requestedRole, $data, $audit) {
            $user = User::with('role')->lockForUpdate()->findOrFail($user->id);
            $activeAdminIds = User::where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'system_administrator'))
                ->lockForUpdate()
                ->pluck('id');

            if ($user->role?->name === 'system_administrator'
                && $user->is_active
                && $requestedRole->name !== 'system_administrator'
                && $activeAdminIds->count() <= 1) {
                abort(422, 'At least one active system administrator must remain.');
            }

            $oldRole = $user->role_id;
            $user->update($data);
            $audit->record($oldRole === $user->role_id ? 'user.updated' : 'user.role_changed', $user, ['role_id' => $user->role_id]);
        }, 3);

        return back()->with('success', 'User account updated.');
    }

    public function toggle(Request $request, User $user, Audit $audit)
    {
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');
        DB::transaction(function () use ($user, $audit) {
            $user = User::with('role')->lockForUpdate()->findOrFail($user->id);
            $activeAdminIds = User::where('is_active', true)
                ->whereHas('role', fn ($query) => $query->where('name', 'system_administrator'))
                ->lockForUpdate()
                ->pluck('id');

            if ($user->is_active && $user->role?->name === 'system_administrator' && $activeAdminIds->count() <= 1) {
                abort(422, 'At least one active system administrator must remain.');
            }

            $user->update(['is_active' => ! $user->is_active]);
            $audit->record($user->is_active ? 'user.activated' : 'user.deactivated', $user);
        }, 3);

        return back()->with('success', $user->is_active ? 'Account reactivated.' : 'Account deactivated.');
    }

    public function import(Request $request, Audit $audit)
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($request->file('csv')->getRealPath(), 'rb');
        if (! $handle) {
            return back()->withErrors(['csv' => 'The uploaded file could not be read.']);
        }
        $headers = fgetcsv($handle);
        $required = ['employee_number', 'first_name', 'middle_name', 'last_name', 'email', 'contact_number', 'position', 'department_code', 'role'];
        $headers = array_map(fn ($v) => trim((string) $v, " \t\n\r\0\x0B\xEF\xBB\xBF"), $headers ?: []);
        if ($headers !== $required) {
            fclose($handle);

            return back()->withErrors(['csv' => 'CSV headers must match the supplied template in the same order.']);
        }
        $roles = Role::pluck('id', 'name');
        $departments = Department::where('is_active', true)->pluck('id', 'code');
        $rows = [];
        $errors = [];
        $line = 1;
        $seenEmails = [];
        $seenNumbers = [];
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($rows) >= 500) {
                $errors[] = 'The CSV cannot contain more than 500 accounts.';
                break;
            }
            if (count($values) !== count($required)) {
                $errors[] = "Row {$line}: expected ".count($required).' columns.';

                continue;
            }
            $row = array_combine($required, array_map(fn ($v) => trim((string) $v), $values));
            $data = [...$row, 'department_id' => $row['department_code'] === '' ? null : ($departments[$row['department_code']] ?? null), 'role_id' => $roles[$row['role']] ?? null];
            $validator = Validator::make($data, [
                'employee_number' => ['nullable', 'string', 'max:40', 'unique:users,employee_number'],
                'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'contact_number' => ['nullable', 'string', 'max:40'], 'position' => ['nullable', 'string', 'max:150'],
                'department_id' => ['nullable', Rule::exists('departments', 'id')->where('is_active', true)], 'role_id' => ['required', 'exists:roles,id'],
            ]);
            if ($row['role'] === '' || ! isset($roles[$row['role']])) {
                $validator->errors()->add('role', 'The role must match an existing role name.');
            }
            if ($row['department_code'] !== '' && ! isset($departments[$row['department_code']])) {
                $validator->errors()->add('department_code', 'The department code does not exist.');
            }
            $emailKey = strtolower($row['email']);
            if (isset($seenEmails[$emailKey])) {
                $validator->errors()->add('email', 'Email is duplicated within this file.');
            } else {
                $seenEmails[$emailKey] = true;
            }
            if ($row['employee_number'] !== '') {
                if (isset($seenNumbers[$row['employee_number']])) {
                    $validator->errors()->add('employee_number', 'Employee number is duplicated within this file.');
                } else {
                    $seenNumbers[$row['employee_number']] = true;
                }
            }
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "Row {$line}: {$message}";
                }

                continue;
            }
            $rows[] = ['data' => $data, 'row' => $row];
        }
        fclose($handle);
        if ($errors) {
            return back()->withErrors(['csv' => implode(' ', $errors)]);
        }
        if (! $rows) {
            return back()->withErrors(['csv' => 'The CSV contains no account rows.']);
        }
        $created = DB::transaction(function () use ($rows, $audit) {
            $users = [];
            foreach ($rows as $item) {
                $row = $item['row'];
                $user = User::create(['employee_number' => $row['employee_number'] ?: null, 'first_name' => $row['first_name'], 'middle_name' => $row['middle_name'] ?: null, 'last_name' => $row['last_name'], 'name' => trim($row['first_name'].' '.($row['middle_name'] ?? '').' '.$row['last_name']), 'email' => $row['email'], 'contact_number' => $row['contact_number'] ?: null, 'position' => $row['position'] ?: null, 'department_id' => $item['data']['department_id'], 'role_id' => $item['data']['role_id'], 'password' => Str::random(64), 'is_active' => true]);
                $audit->record('user.created_by_import', $user, ['source' => 'csv', 'role_id' => $user->role_id]);
                $users[] = $user;
            }

            return $users;
        });
        $deliveryMessages = collect($created)->map(fn (User $user) => $this->sendPasswordSetupLink($user))->unique()->values();

        return back()->with('success', count($created).' accounts imported. '.$deliveryMessages->implode(' '));
    }

    public function template()
    {
        return response("employee_number,first_name,middle_name,last_name,email,contact_number,position,department_code,role\r\nEMP-0001,Alex,,Rivera,alex@example.edu,09170000000,Faculty,ACAD,faculty_employee\r\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename=umis-user-import-template.csv']);
    }

    public function sendPasswordReset(User $user, Audit $audit)
    {
        abort_if(! $user->is_active, 422, 'Reactivate this account before sending a password setup link.');
        abort_if(! $user->email, 422, 'This account has no email. Generate a temporary password instead.');
        $deliveryMessage = $this->sendPasswordSetupLink($user);
        $audit->record('user.password_reset_link_sent', $user);

        return back()->with('success', $deliveryMessage);
    }

    public function generateTemporaryPassword(User $user, Audit $audit)
    {
        abort_if(! $user->is_active, 422, 'Reactivate this account before resetting its password.');
        $temporaryPassword = $this->temporaryPassword();
        $user->forceFill(['password' => $temporaryPassword, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        $audit->record('user.temporary_password_generated', $user);

        return back()
            ->with('success', 'Temporary password generated. Give it to the user through an approved private channel; it must be changed at first sign-in.')
            ->with('temporary_password', $temporaryPassword);
    }

    private function sendPasswordSetupLink(User $user): string
    {
        if (! $user->email) {
            return 'This account has no email address. Generate a temporary password instead.';
        }

        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);

            return 'The account exists, but email delivery failed. Check the configured MAIL_* settings.';
        }

        if ($status === Password::RESET_THROTTLED) {
            return 'A setup link was generated recently. Wait one minute before requesting another and use the latest link.';
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return 'The account exists, but the setup link could not be generated. Check the password broker configuration.';
        }

        if (config('mail.default') === 'log') {
            return 'Local mail is in log mode, so no email was delivered. Copy the newest setup URL from storage/logs/laravel.log and open it on the computer running this local system within 60 minutes.';
        }

        return 'A password setup email was sent through the configured mail transport.';
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $departmentRule = Rule::exists('departments', 'id')->where(fn ($query) => $query
            ->where('is_active', true)
            ->when($user?->department_id, fn ($query, $id) => $query->orWhere('id', $id)));

        return $request->validate([
            'employee_number' => ['nullable', 'required_without:email', 'string', 'max:40', Rule::unique('users', 'employee_number')->ignore($user?->id)],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'required_without:employee_number', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)], 'contact_number' => ['nullable', 'string', 'max:40'], 'position' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', $departmentRule], 'role_id' => ['required', 'exists:roles,id'],
        ]);
    }

    private function temporaryPassword(): string
    {
        return Str::random(24).'aA8!';
    }
}

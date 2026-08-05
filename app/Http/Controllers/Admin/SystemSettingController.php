<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'group_id' => $request->query('group_id', ''),
            'status' => $request->query('status', ''),
            'value_type' => $request->query('value_type', ''),
        ];

        $settings = SystemSetting::query()
            ->with('group')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->where('key', 'like', '%'.$filters['search'].'%')
                        ->orWhere('label', 'like', '%'.$filters['search'].'%')
                        ->orWhere('value', 'like', '%'.$filters['search'].'%')
                        ->orWhere('description', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when((int) $filters['group_id'] > 0, fn ($query) => $query->where('group_id', (int) $filters['group_id']))
            ->when(in_array($filters['status'], [SystemSetting::STATUS_ACTIVE, SystemSetting::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(in_array($filters['value_type'], SystemSetting::valueTypes(), true), fn ($query) => $query->where('value_type', $filters['value_type']))
            ->orderBy(
                SystemSettingGroup::query()
                    ->select('sort_order')
                    ->whereColumn('system_setting_groups.id', 'system_settings.group_id')
                    ->limit(1),
            )
            ->orderBy('sort_order')
            ->orderBy('key')
            ->paginate(20)
            ->withQueryString();

        return view('admin.system-settings.index', [
            'settings' => $settings,
            'filters' => $filters,
            'groups' => $this->groups(),
            'valueTypes' => SystemSetting::valueTypes(),
            'statusOptions' => [SystemSetting::STATUS_ACTIVE => 'Active', SystemSetting::STATUS_INACTIVE => 'Inactive'],
        ]);
    }

    public function edit(SystemSetting $systemSetting): View
    {
        abort_if($systemSetting->trashed(), 404);

        return view('admin.system-settings.edit', [
            'setting' => $systemSetting->load('group'),
            'groups' => $this->groups(),
            'valueTypes' => SystemSetting::valueTypes(),
            'statusOptions' => [SystemSetting::STATUS_ACTIVE => 'Active', SystemSetting::STATUS_INACTIVE => 'Inactive'],
        ]);
    }

    public function update(Request $request, SystemSetting $systemSetting): RedirectResponse
    {
        abort_if($systemSetting->trashed(), 404);

        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('system_setting_groups', 'id')->whereNull('deleted_at')],
            'label' => ['required', 'string', 'max:150'],
            'value' => ['nullable', 'string'],
            'value_type' => ['required', Rule::in(SystemSetting::valueTypes())],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([SystemSetting::STATUS_ACTIVE, SystemSetting::STATUS_INACTIVE])],
            'is_public' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
        ]);

        $data['value'] = $this->normalizeValue($data['value'] ?? null, $data['value_type']);
        $data['is_public'] = $request->boolean('is_public');
        $data['is_encrypted'] = $request->boolean('is_encrypted');
        $data['updated_by'] = Auth::id();

        $systemSetting->forceFill($data)->save();

        return redirect()->route('admin.system-settings.edit', $systemSetting)->with('success', 'System setting updated successfully.');
    }

    private function normalizeValue(?string $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === SystemSetting::TYPE_INTEGER && filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['value' => 'The value must be a valid integer.']);
        }

        if ($type === SystemSetting::TYPE_BOOLEAN && ! in_array($value, ['0', '1', 'true', 'false'], true)) {
            throw ValidationException::withMessages(['value' => 'The value must be true or false.']);
        }

        if (in_array($type, [SystemSetting::TYPE_JSON, SystemSetting::TYPE_ARRAY], true)) {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    private function groups()
    {
        return SystemSettingGroup::query()
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}

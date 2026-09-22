<?php

namespace App\Http\Controllers;

use App\Models\OfficeShift;
use App\Models\User;
use App\Models\WorkScheduleRule;
use App\Models\WorkSuspension;
use App\Services\WorkScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkScheduleController extends Controller
{
    public function index(Request $request, WorkScheduleResolver $resolver)
    {
        abort_unless((int) $request->user()?->role === 1, 403);
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = CarbonImmutable::parse(($input['month'] ?? now()->format('Y-m')).'-01');
        $from = $month->startOfWeek();
        $to = $month->endOfMonth()->endOfWeek();
        $resolver->load($from, $to);
        $shifts = OfficeShift::with('schedules')->orderBy('name')->get();
        $users = User::where('status', true)->get(['id', 'name', 'office_shift_id']);
        $individualScopes = WorkScheduleRule::whereNotNull('user_id')->pluck('user_id')->all();
        $suggestions = [];
        // Review one representative employee for each scope; individual rules may take precedence.
        foreach ($users->groupBy('office_shift_id') as $shiftUsers) {
            $representatives = $shiftUsers->filter(fn ($user) => in_array($user->id, $individualScopes));
            $general = $shiftUsers->first(fn ($user) => !$representatives->contains('id', $user->id));
            if ($general) $representatives = $representatives->prepend($general);
            foreach ($representatives as $user) {
                $user->setRelation('officeShift', $shifts->firstWhere('id', $user->office_shift_id));
                for ($week = $from; $week <= $to; $week = $week->addWeek()) {
                    $profile = $resolver->resolve($user, $week->toDateString());
                    $fridayEvents = array_filter($resolver->events($week->addDays(4)->toDateString(), $user->office_shift_id),
                        fn ($event) => !$event['is_working_day']);
                    if (!$profile || empty($profile['friday_exception']) || in_array(5, $profile['working_days'] ?? []) || !$fridayEvents || $profile['_source'] === 'Weekly exception') continue;
                    $suggestions[] = ['office_shift_id' => $user->office_shift_id,
                        'user_id' => $general && $general->id === $user->id ? null : $user->id,
                        'scope' => $general && $general->id === $user->id ? $user->officeShift?->name : $user->name,
                        'week_start' => $week->toDateString(), 'week_end' => $week->addDays(6)->toDateString(),
                        'reason' => 'Friday holiday / suspension: '.implode(', ', array_column($fridayEvents, 'name'))];
                }
            }
        }
        return response()->json(['month' => $month->format('Y-m'), 'profiles' => WorkScheduleResolver::profiles(),
            'office_shifts' => $shifts, 'users' => $users, 'suggestions' => $suggestions,
            'rules' => WorkScheduleRule::with(['officeShift:id,name', 'user:id,name', 'creator:id,name'])
                ->where('kind', '!=', 'baseline')->orderByDesc('effective_from')->get(),
            'suspensions' => WorkSuspension::with('officeShift:id,name')->orderByDesc('suspension_date')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless((int) $request->user()?->role === 1, 403);
        $input = $request->validate([
            'office_shift_id' => ['required', 'integer', 'exists:office_shifts,id,deleted_at,NULL'],
            'user_id' => ['nullable', 'integer', 'exists:users,id,deleted_at,NULL'],
            'kind' => ['required', Rule::in(['permanent', 'weekly'])],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-01'],
            'profile_key' => ['required', Rule::in(['compressed', 'standard', 'existing'])],
            'source_shift_id' => ['required_if:profile_key,existing', 'nullable', 'integer', 'exists:office_shifts,id,deleted_at,NULL'],
            'working_days' => ['required', 'array', 'min:1', 'max:7'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $date = CarbonImmutable::parse($input['effective_from']);
        if ($input['kind'] === 'weekly' && !$date->isMonday()) {
            throw ValidationException::withMessages(['effective_from' => 'Choose the Monday starting this exception week.']);
        }
        $rule = DB::transaction(function () use ($input, $date, $request) {
            $shift = OfficeShift::with('schedules')->lockForUpdate()->findOrFail($input['office_shift_id']);
            $userId = $input['user_id'] ?? null;
            if ($userId && !User::whereKey($userId)->where('office_shift_id', $shift->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'Employee must belong to the selected office shift.']);
            }
            $scope = WorkScheduleRule::where('office_shift_id', $shift->id)->where('user_id', $userId);
            if ((clone $scope)->where('kind', $input['kind'])->where('effective_from', $input['effective_from'])->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'A schedule already exists for this scope and start date. Remove it before replacing it.']);
            }
            // Snapshot the untouched shift before the first dated rule, including for weekly-only users.
            if (!WorkScheduleRule::where('office_shift_id', $shift->id)->whereNull('user_id')->where('kind', 'baseline')->exists()) {
                WorkScheduleRule::create(['office_shift_id' => $shift->id, 'kind' => 'baseline', 'effective_from' => '1900-01-01',
                    'profile' => $shift->toArray(), 'reason' => 'Original shift before effective-dated scheduling', 'created_by' => $request->user()->id]);
            }
            $profile = $input['profile_key'] === 'existing'
                ? OfficeShift::with('schedules')->findOrFail($input['source_shift_id'])->toArray()
                : array_merge(array_intersect_key($shift->toArray(), array_flip(['grace_enabled', 'grace_before_minutes', 'grace_after_minutes'])), WorkScheduleResolver::profiles()[$input['profile_key']]);
            $profile['working_days'] = array_map('intval', $input['working_days']);
            return WorkScheduleRule::create(['office_shift_id' => $shift->id, 'user_id' => $userId,
                'kind' => $input['kind'], 'effective_from' => $input['effective_from'],
                'effective_to' => $input['kind'] === 'weekly' ? $date->addDays(6)->toDateString() : null,
                'profile' => $profile, 'reason' => $input['reason'], 'created_by' => $request->user()->id]);
        });
        return response()->json(['message' => 'Schedule saved', 'rule' => $rule]);
    }

    public function delete(Request $request)
    {
        abort_unless((int) $request->user()?->role === 1, 403);
        $input = $request->validate(['id' => ['required', 'integer']]);
        $rule = WorkScheduleRule::findOrFail($input['id']);
        abort_if($rule->kind === 'baseline', 422, 'Historical baselines cannot be removed.');
        $rule->delete();
        return response()->json(['message' => 'Schedule removed; the previous applicable schedule is used again.']);
    }

    public function storeSuspension(Request $request)
    {
        abort_unless((int) $request->user()?->role === 1, 403);
        $input = $request->validate(['name' => ['required', 'string', 'max:120'],
            'suspension_date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['required', Rule::in(['full_day', 'morning', 'afternoon'])],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id,deleted_at,NULL']]);
        return response()->json(['suspension' => WorkSuspension::create([...$input, 'created_by' => $request->user()->id])]);
    }

    public function deleteSuspension(Request $request)
    {
        abort_unless((int) $request->user()?->role === 1, 403);
        $input = $request->validate(['id' => ['required', 'integer']]);
        WorkSuspension::findOrFail($input['id'])->delete();
        return response()->json(['message' => 'Suspension removed']);
    }
}

<?php

namespace App\Services;

class MonthlyReportPage
{
    public function apply(array $data, array $filters): array
    {
        $all = collect($data['employees']);
        // Derive columns from the complete report so paging never changes the table layout.
        $periods = [];
        $calendar = array_column($data['days'], null, 'date');
        $excluded = array_fill_keys(array_column($data['days'], 'date'), $all->isNotEmpty());
        foreach ($all as $employee) {
            foreach ($employee['days'] as $day) {
                $date = $day['date'];
                $excluded[$date] = ($excluded[$date] ?? true)
                    && (($day['rest_day'] ?? false) || (($day['working_day'] ?? null) === null && ($calendar[$date]['weekend'] ?? false)));
                foreach ($day['periods'] as $period) {
                    $key = $period['next_day'] ? '1-'.$period['scheduled_out']
                        : ($period['scheduled_out'] === '12:00' ? 'AM'
                            : ($period['scheduled_in'] === '13:00' ? 'PM' : '0-'.$period['scheduled_out']));
                    $periods[$key] = [
                        'key' => $key, 'end' => $period['scheduled_out'], 'nextDay' => $period['next_day'],
                        'label' => in_array($key, ['AM', 'PM'], true) ? $key
                            : ($period['next_day'] ? $period['scheduled_out'].' +1'
                                : ($period['scheduled_out'] < '12:00' ? 'Early' : $period['scheduled_out'])),
                    ];
                }
            }
        }
        if ($all->isEmpty()) {
            $excluded = collect($data['days'])->mapWithKeys(fn ($day) => [$day['date'] => $day['weekend']])->all();
        }
        $data['periods'] = collect($periods)->sortBy([['nextDay', 'asc'], ['end', 'asc']])->values()->all();
        $data['excluded_dates'] = $excluded;
        $term = mb_strtolower(trim($filters['search'] ?? ''));
        $employees = $all->filter(fn ($employee) =>
            ($term === '' || str_contains(mb_strtolower($employee['name'].' '.$employee['shift_name']), $term))
            && (empty($filters['issues_only']) || $employee['total_minutes'] > 0));
        $sort = $filters['sort'] ?? 'late_count';
        $employees = $employees->sort(function ($a, $b) use ($sort) {
            $name = strnatcasecmp($a['name'], $b['name']);
            return ($sort === 'name' ? $name : (($b[$sort] <=> $a[$sort]) ?: ($b['total_minutes'] <=> $a['total_minutes']) ?: $name))
                ?: ($a['id'] <=> $b['id']);
        })->values();
        $data['summary'] = [
            'count' => $employees->count(), 'minutes' => $employees->sum('total_minutes'),
            'late' => $employees->sum('late_count'), 'under' => $employees->sum('undertime_count'),
        ];
        if (isset($filters['per_page']) && empty($filters['export_all'])) {
            $size = (int) $filters['per_page'];
            $last = max(1, (int) ceil($employees->count() / $size));
            $page = min((int) ($filters['page'] ?? 1), $last);
            $data['employees'] = $employees->forPage($page, $size)->values()->all();
            $data['pagination'] = [
                'page' => $page, 'per_page' => $size, 'total' => $employees->count(), 'last_page' => $last,
                'from' => $employees->isEmpty() ? 0 : ($page - 1) * $size + 1,
                'to' => min($page * $size, $employees->count()),
            ];
        } else {
            $data['employees'] = $employees->all();
        }

        return $data;
    }
}

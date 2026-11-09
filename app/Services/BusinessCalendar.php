<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BusinessCalendar
{
    public function addWorkingMinutes(CarbonImmutable $start, int $minutes): CarbonImmutable
    {
        $settings = DB::table('system_settings')->pluck('value', 'key');
        $startTime = $settings['working_start'] ?? '08:00';
        $endTime = $settings['working_end'] ?? '17:00';
        $cursor = $start;
        $remaining = max(0, $minutes);
        while ($remaining > 0) {
            if (! $this->isWorkday($cursor, $settings['working_days'] ?? '1,2,3,4,5')) {
                $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);

                continue;
            }
            $dayStart = $cursor->setTimeFromTimeString($startTime);
            $dayEnd = $cursor->setTimeFromTimeString($endTime);
            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart;
            }
            if ($cursor->gte($dayEnd)) {
                $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);

                continue;
            }
            $available = (int) floor($cursor->diffInSeconds($dayEnd) / 60);
            if ($remaining <= $available) {
                return $cursor->addMinutes($remaining);
            }
            $remaining -= $available;
            $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);
        }

        return $cursor;
    }

    public function workingMinutesBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $sign = 1;
        if ($end->lt($start)) {
            [$start,$end] = [$end, $start];
            $sign = -1;
        }
        $settings = DB::table('system_settings')->pluck('value', 'key');
        $startTime = $settings['working_start'] ?? '08:00';
        $endTime = $settings['working_end'] ?? '17:00';
        $days = $settings['working_days'] ?? '1,2,3,4,5';
        $cursor = $start;
        $minutes = 0;
        while ($cursor->lt($end)) {
            if (! $this->isWorkday($cursor, $days)) {
                $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);

                continue;
            }
            $dayStart = $cursor->setTimeFromTimeString($startTime);
            $dayEnd = $cursor->setTimeFromTimeString($endTime);
            if ($cursor->lt($dayStart)) {
                $cursor = $dayStart;
            }
            if ($cursor->gte($dayEnd)) {
                $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);

                continue;
            }
            $segmentEnd = $end->lt($dayEnd) ? $end : $dayEnd;
            $minutes += (int) floor($cursor->diffInSeconds($segmentEnd) / 60);
            $cursor = $segmentEnd;
            if ($cursor->lt($end)) {
                $cursor = $cursor->addDay()->startOfDay()->setTimeFromTimeString($startTime);
            }
        }

        return $minutes * $sign;
    }

    private function isWorkday(CarbonImmutable $date, string $workingDays): bool
    {
        $holiday = Holiday::whereDate('date', $date->toDateString())->first();
        if ($holiday) {
            return $holiday->is_working_day;
        }

        return in_array((string) $date->dayOfWeek, explode(',', $workingDays), true);
    }
}

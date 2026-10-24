<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PlanScheduleService
{
    public function suggest(User $actor, Plan $base, string $from): Plan
    {
        abort_unless(app(PlanService::class)->allowed($actor, $base->workspace, $base->project, 'plan.update'), 403);
        $payload = app(PlanService::class)->payload($base);
        $payload['slots'] = [];
        $zone = $base->workspace->timezone;
        $hours = app(WorkHoursService::class)->values($actor, $zone);
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, $zone);
        if ($start->lt(CarbonImmutable::now($zone)->startOfDay())) {
            throw ValidationException::withMessages(['from' => 'Chọn hôm nay hoặc ngày tương lai.']);
        }
        $busy = CalendarEvent::where('user_id', $actor->id)->where('is_busy', true)->where('status', '!=', 'cancelled')->where('ends_at', '>', $start->utc())->where('starts_at', '<', $start->addDays(93)->utc())->get();
        $pending = array_column($payload['tasks'], null, 'ref');
        $finish = [];
        foreach ($pending as &$row) {
            if (isset($base->data['applied_task_ids'][$row['ref']])) {
                $row['task_id'] = $base->data['applied_task_ids'][$row['ref']];
            }
        }unset($row);
        $tasks = $pending;
        while ($pending) {
            $progress = false;
            foreach ($pending as $ref => $row) {
                if ($row['task_type'] === 'GROUP') {
                    $finish[$ref] = $start;
                    unset($pending[$ref]);
                    $progress = true;

                    continue;
                }
                $earliest = $start;
                foreach ($payload['dependencies'] as $edge) {
                    if ($edge['successor'] === $ref) {
                        if (! isset($finish[$edge['predecessor']])) {
                            continue 2;
                        }$earliest = $earliest->max($finish[$edge['predecessor']]->addMinutes($edge['lag_minutes']));
                    }
                }
                if ($row['task_type'] === 'MILESTONE') {
                    $finish[$ref] = $earliest;
                    unset($pending[$ref]);
                    $progress = true;

                    continue;
                }
                if (($row['assignee_id'] ?? null) !== $actor->id || empty($row['estimate_minutes'])) {
                    throw ValidationException::withMessages(['plan' => 'Xếp lịch tự động chỉ nhận WORK giao cho bạn, có ước lượng lớn hơn 0.']);
                }
                $remaining = (int) $row['estimate_minutes'];
                $last = $earliest;
                for ($day = $start->setTimezone($hours['timezone'])->startOfDay(); $remaining > 0 && $day->lt($start->addDays(93)); $day = $day->addDay()) {
                    if (! in_array($day->dayOfWeekIso, $hours['weekdays'], true)) {
                        continue;
                    }
                    foreach ($hours['windows'] as $window) {
                        try {
                            $cursor = app(CalendarService::class)->localTime($day->format('Y-m-d').'T'.$window['start'], $hours['timezone'], false)->setTimezone($hours['timezone']);
                            $end = app(CalendarService::class)->localTime($day->format('Y-m-d').'T'.$window['end'], $hours['timezone'], false)->setTimezone($hours['timezone']);
                        } catch (ValidationException) {
                            continue;
                        }
                        while ($remaining > 0 && $cursor->lt($end)) {
                            $size = min((int) $hours['slot_minutes'], $remaining);
                            $next = $cursor->addMinutes($size);
                            if ($next->gt($end)) {
                                break;
                            }
                            if ($cursor->gte($earliest) && $cursor->gte(CarbonImmutable::now($zone))) {
                                $conflict = $busy->contains(fn ($e) => $e->starts_at->lt($next->utc()) && $e->ends_at->gt($cursor->utc()));
                                foreach ($payload['slots'] as $s) {
                                    if ($s['starts_at'] < $next->setTimezone($zone)->format('Y-m-d\TH:i') && $s['ends_at'] > $cursor->setTimezone($zone)->format('Y-m-d\TH:i')) {
                                        $conflict = true;
                                    }
                                }
                                if (! $conflict) {
                                    try {
                                        app(CalendarService::class)->localTime($cursor->setTimezone($zone)->format('Y-m-d\TH:i'), $zone, false);
                                        app(CalendarService::class)->localTime($next->setTimezone($zone)->format('Y-m-d\TH:i'), $zone, false);
                                    } catch (ValidationException) {
                                        $cursor = $next;

                                        continue;
                                    }
                                    $payload['slots'][] = ['task_ref' => $ref, 'starts_at' => $cursor->setTimezone($zone)->format('Y-m-d\TH:i'), 'ends_at' => $next->setTimezone($zone)->format('Y-m-d\TH:i')];
                                    $remaining -= $size;
                                    $last = $next;
                                }
                            }
                            $cursor = $next;
                            if (count($payload['slots']) > 300) {
                                throw ValidationException::withMessages(['plan' => 'Đề xuất vượt 300 khung giờ; chia nhỏ kế hoạch.']);
                            }
                        }
                    }
                }
                if ($remaining > 0) {
                    throw ValidationException::withMessages(['plan' => 'Không đủ thời gian trống trong 93 ngày.']);
                }
                $finish[$ref] = $last;
                unset($pending[$ref]);
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages(['plan' => 'Không thể xếp dependency.']);
            }
        }
        $payload['tasks'] = array_values($tasks);

        return app(PlanService::class)->create($actor, $base->workspace, $base->project, $base->name.' — xếp lịch', $payload, $base);
    }
}

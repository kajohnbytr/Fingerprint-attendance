<?php

namespace App;

use App\Models\Schedule;
use App\Models\Subject;
use Illuminate\Support\Collection;

class ScheduleConflicts
{
    /**
     * Compare both saved schedule sources. Exact legacy copies of subject schedules
     * are omitted so they do not create conflicts with themselves.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $rows = Subject::with(['faculty', 'enrollments.block'])->get()->map(fn (Subject $subject): array => [
            'key' => 'subject-'.$subject->id,
            'source' => 'Subject',
            'subject_id' => $subject->id,
            'code' => $subject->code,
            'description' => $subject->description,
            'sections' => $subject->enrollments->pluck('block.name', 'block_id')->filter()->all(),
            'faculty_id' => $subject->faculty_id,
            'faculty' => $subject->faculty?->name ?? '',
            'room' => $subject->room,
            'day' => $subject->day,
            'start' => $subject->start_time->format('H:i'),
            'end' => $subject->end_time->format('H:i'),
            'conflicts' => [],
        ]);

        foreach (Schedule::with('block')->get() as $schedule) {
            $copy = $rows->first(fn (array $row): bool => $row['code'] === $schedule->subject_code && $row['day'] === $schedule->day
                && $row['start'] === substr($schedule->start_time, 0, 5) && $row['end'] === substr($schedule->end_time, 0, 5)
                && $this->normalize($row['room']) === $this->normalize($schedule->room)
                && $this->normalize($row['faculty']) === $this->normalize($schedule->instructor)
                && array_key_exists($schedule->block_id, $row['sections']));
            if ($copy) {
                continue;
            }
            $subject = $rows->firstWhere('code', $schedule->subject_code);
            $rows->push([
                'key' => 'schedule-'.$schedule->id, 'source' => 'Class schedule',
                'subject_id' => $subject['subject_id'] ?? null,
                'code' => $schedule->subject_code, 'description' => $schedule->subject_name,
                'sections' => [$schedule->block_id => $schedule->block?->name ?? 'Unassigned'],
                'faculty_id' => $subject && $this->normalize($subject['faculty']) === $this->normalize($schedule->instructor) ? $subject['faculty_id'] : null,
                'faculty' => $schedule->instructor, 'room' => $schedule->room, 'day' => $schedule->day,
                'start' => substr($schedule->start_time, 0, 5), 'end' => substr($schedule->end_time, 0, 5), 'conflicts' => [],
            ]);
        }

        $result = $rows->values()->all();
        for ($first = 0; $first < count($result); $first++) {
            for ($second = $first + 1; $second < count($result); $second++) {
                $left = $result[$first];
                $right = $result[$second];
                if ($left['day'] !== $right['day'] || $left['start'] >= $right['end'] || $right['start'] >= $left['end']) {
                    continue;
                }
                $reasons = [];
                if ($this->normalize($left['room']) !== '' && $this->normalize($left['room']) === $this->normalize($right['room'])) {
                    $reasons[] = 'Room';
                }
                if ($this->normalize($left['faculty']) !== '' && $this->normalize($left['faculty']) === $this->normalize($right['faculty'])) {
                    $reasons[] = 'Faculty';
                }
                if (array_intersect_key($left['sections'], $right['sections'])) {
                    $reasons[] = 'Section';
                }
                if ($reasons) {
                    $result[$first]['conflicts'][] = implode(', ', $reasons).' overlap with '.$right['code'].' ('.$right['key'].')';
                    $result[$second]['conflicts'][] = implode(', ', $reasons).' overlap with '.$left['code'].' ('.$left['key'].')';
                }
            }
        }

        $weekdays = array_flip(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

        return collect($result)->sortBy(fn (array $row): string => ($weekdays[$row['day']] ?? 7).$row['start'].$row['code'])->values();
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim($value ?? ''));
    }
}

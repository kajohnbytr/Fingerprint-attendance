<?php

namespace App;

use App\Models\Block;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClassScheduleManager
{
    /** @return array{day: string, start_time: string, end_time: string, room: string} */
    public function effective(Subject $subject, int $blockId): array
    {
        $schedule = $subject->classSchedules->firstWhere('block_id', $blockId);

        return [
            'day' => $schedule?->day ?? $subject->day,
            'start_time' => $schedule?->start_time?->format('H:i') ?? $subject->start_time->format('H:i'),
            'end_time' => $schedule?->end_time?->format('H:i') ?? $subject->end_time->format('H:i'),
            'room' => $schedule?->room ?? $subject->room,
        ];
    }

    /**
     * @param  array{day: string, start_time: string, end_time: string, room: string}  $candidate
     */
    public function ensureAvailable(Subject $subject, Block $block, array $candidate): void
    {
        $conflicts = $this->rows()
            ->reject(fn (array $row): bool => $row['subject_id'] === $subject->id && $row['block_id'] === $block->id)
            ->reject(fn (array $row): bool => $row['subject_id'] === $subject->id && ! $row['is_explicit'])
            ->filter(fn (array $row): bool => $row['day'] === $candidate['day']
                && $candidate['start_time'] < $row['end_time']
                && $row['start_time'] < $candidate['end_time'])
            ->map(function (array $row) use ($subject, $block, $candidate): ?string {
                $reasons = [];
                if ($row['faculty_id'] === $subject->faculty_id) {
                    $reasons[] = 'faculty';
                }
                if ($row['block_id'] === $block->id) {
                    $reasons[] = 'block';
                }
                if (mb_strtolower(trim($row['room'])) === mb_strtolower(trim($candidate['room']))) {
                    $reasons[] = 'room';
                }

                return $reasons
                    ? ucfirst(implode(', ', $reasons))." conflict with {$row['code']} — {$row['block']} ({$row['start_time']}–{$row['end_time']})."
                    : null;
            })
            ->filter()
            ->values();

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['start_time' => $conflicts->all()]);
        }
    }

    /** @return Collection<int, array{subject_id: int, block_id: int, block: string, faculty_id: ?int, code: string, day: string, start_time: string, end_time: string, room: string, is_explicit: bool}> */
    public function rows(): Collection
    {
        return Subject::query()
            ->with(['enrollments.block', 'classSchedules'])
            ->get()
            ->flatMap(function (Subject $subject): array {
                return $subject->enrollments
                    ->whereNotNull('block_id')
                    ->groupBy('block_id')
                    ->map(function ($enrollments) use ($subject): array {
                        $block = $enrollments->first()->block;
                        $isExplicit = $subject->classSchedules->contains('block_id', $block->id);
                        $effective = $this->effective($subject, $block->id);

                        return [
                            'subject_id' => $subject->id,
                            'block_id' => $block->id,
                            'block' => $block->name,
                            'faculty_id' => $subject->faculty_id,
                            'code' => $subject->code,
                            'is_explicit' => $isExplicit,
                            ...$effective,
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalUtilityController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->merge(['q' => trim((string) $request->input('q'))]);
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $user = $request->user();
        $terms = preg_split('/\s+/', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $students = Student::query()
            ->when($user->role === 'faculty', fn (Builder $query) => $query->whereHas(
                'subjects',
                fn (Builder $subjects) => $subjects->where('faculty_id', $user->id)
            ))
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function (Builder $matches) use ($term): void {
                        $pattern = '%'.$term.'%';
                        $matches->where('student_number', 'like', $pattern)
                            ->orWhere('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                            ->orWhere('email', 'like', $pattern);
                    });
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(5)
            ->get();

        $results = $students->map(fn (Student $student): array => [
            'type' => 'Student',
            'title' => $student->full_name,
            'detail' => $student->student_number.' · '.$student->email,
            'url' => route('students.show', $student),
        ]);

        if (in_array($user->role, ['admin', 'program_head'], true)) {
            $faculty = User::query()
                ->where('role', 'faculty')
                ->where(function (Builder $query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->where(function (Builder $matches) use ($term): void {
                            $pattern = '%'.$term.'%';
                            $matches->where('name', 'like', $pattern)
                                ->orWhere('email', 'like', $pattern);
                        });
                    }
                })
                ->orderBy('name')
                ->limit(4)
                ->get();

            foreach ($faculty as $member) {
                $results->push([
                    'type' => 'Faculty',
                    'title' => $member->name,
                    'detail' => $member->email,
                    'url' => route('program-head.faculty.index', ['search' => $data['q']]),
                ]);
            }
        }

        $subjects = Subject::query()
            ->when($user->role === 'faculty', fn (Builder $query) => $query->where('faculty_id', $user->id))
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->where(function (Builder $matches) use ($term): void {
                        $pattern = '%'.$term.'%';
                        $matches->where('code', 'like', $pattern)
                            ->orWhere('description', 'like', $pattern);
                    });
                }
            })
            ->orderBy('code')
            ->limit(4)
            ->get();

        foreach ($subjects as $subject) {
            $results->push([
                'type' => 'Subject',
                'title' => $subject->code,
                'detail' => $subject->description,
                'url' => $user->role === 'faculty'
                    ? route('faculty.schedules', ['subject_id' => $subject->id])
                    : route('program-head.subjects.index').'#subject-'.$subject->id,
            ]);
        }

        return response()->json([
            'results' => $results->take(10)->values(),
            'studentsUrl' => route('students.index', ['search' => $data['q']]),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $inboxUrl = match ($user->role) {
            'admin' => route('admin.inbox'),
            'faculty' => route('faculty.inbox'),
            default => route('program-head.inbox'),
        };
        $announcements = DB::table('portal_announcements')
            ->leftJoin('subjects', 'subjects.id', '=', 'portal_announcements.subject_id')
            ->join('users', 'users.id', '=', 'portal_announcements.author_id')
            ->where('portal_announcements.published_at', '<=', now())
            ->whereNull('portal_announcements.archived_at')
            ->where(fn (QueryBuilder $query) => $query->whereNull('portal_announcements.expires_at')->orWhere('portal_announcements.expires_at', '>', now()))
            ->when($user->role === 'faculty', fn (QueryBuilder $query) => $query->where(function (QueryBuilder $audience) use ($user): void {
                $audience->whereIn('portal_announcements.audience', ['all', 'faculty'])
                    ->orWhere(fn (QueryBuilder $subject) => $subject->where('portal_announcements.audience', 'subject')->where('subjects.faculty_id', $user->id));
            }))
            ->select('portal_announcements.id', 'portal_announcements.title', 'portal_announcements.published_at', 'subjects.code as subject_code', 'users.name as author_name')
            ->orderByDesc('portal_announcements.published_at')
            ->limit(5)
            ->get()
            ->map(fn (object $announcement): array => [
                'id' => 'announcement-'.$announcement->id,
                'type' => 'Announcement',
                'title' => $announcement->title,
                'detail' => ($announcement->subject_code ?? 'Campus-wide').' · '.$announcement->author_name,
                'timestamp' => $announcement->published_at,
                'url' => $user->role === 'admin'
                    ? route('admin.announcements.show', $announcement->id)
                    : $inboxUrl.'#announcement-'.$announcement->id,
            ]);
        $complaints = DB::table('student_complaints')
            ->join('subjects', 'subjects.id', '=', 'student_complaints.subject_id')
            ->join('students', 'students.id', '=', 'student_complaints.student_id')
            ->where('student_complaints.status', '!=', 'resolved')
            ->when($user->role === 'faculty', fn (QueryBuilder $query) => $query->where('subjects.faculty_id', $user->id))
            ->select('student_complaints.id', 'student_complaints.title', 'student_complaints.status', 'student_complaints.created_at', 'subjects.code as subject_code', 'students.first_name', 'students.last_name')
            ->orderByDesc('student_complaints.created_at')
            ->limit(5)
            ->get()
            ->map(fn (object $complaint): array => [
                'id' => 'complaint-'.$complaint->id,
                'type' => 'Student concern',
                'title' => $complaint->title,
                'detail' => trim($complaint->first_name.' '.$complaint->last_name).' · '.$complaint->subject_code.' · '.str_replace('_', ' ', ucfirst($complaint->status)),
                'timestamp' => $complaint->created_at,
                'url' => $user->role === 'admin'
                    ? route('admin.complaints.show', $complaint->id)
                    : $inboxUrl.'#complaint-'.$complaint->id,
            ]);
        $items = $announcements->concat($complaints)
            ->sortByDesc(fn (array $item): int => strtotime($item['timestamp']) ?: 0)
            ->take(8)
            ->values();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
            'inboxUrl' => $inboxUrl,
        ]);
    }
}

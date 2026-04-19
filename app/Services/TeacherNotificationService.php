<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\TeacherNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TeacherNotificationService
{
    /**
     * Build/update notifications from live data and return visible notifications query.
     */
    public function syncAndFetch(User $teacher, int $perPage = 20)
    {
        $this->syncComputedNotifications($teacher);

        $hasClassrooms = $teacher->classrooms()->exists();

        $query = $teacher->teacherNotifications()
            ->select([
                'id',
                'title',
                'message',
                'category',
                'event_type',
                'severity',
                'occurred_at',
                'is_read',
                'read_at',
                'created_at',
            ])
            ->latest('occurred_at');

        if (! $hasClassrooms) {
            $query->where('category', '!=', 'classroom');
        }

        return $query->paginate($perPage);
    }

    public function markAsRead(User $teacher, TeacherNotification $notification): TeacherNotification
    {
        $this->assertOwnership($teacher, $notification);

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return $notification->fresh();
    }

    public function markAllAsRead(User $teacher): int
    {
        return $teacher->teacherNotifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function delete(User $teacher, TeacherNotification $notification): void
    {
        $this->assertOwnership($teacher, $notification);
        $notification->delete();
    }

    protected function assertOwnership(User $teacher, TeacherNotification $notification): void
    {
        if ((int) $notification->user_id !== (int) $teacher->id) {
            abort(403, 'Unauthorized notification access.');
        }
    }

    protected function syncComputedNotifications(User $teacher): void
    {
        $now = now();
        $rows = collect();

        $latestQuiz = $teacher->quizzes()
            ->latest('created_at')
            ->first(['id', 'title', 'created_at']);

        if ($latestQuiz) {
            $rows->push($this->makeRow(
                $teacher->id,
                'ai.quiz_generated.latest_'.$latestQuiz->id,
                'Quiz generated successfully',
                sprintf('Latest quiz ready: "%s".', $latestQuiz->title ?: 'Untitled Quiz'),
                'ai_activity',
                'quiz_generated',
                'success',
                Carbon::parse($latestQuiz->created_at),
                $now
            ));
        }

        $remaining = null;
        if (is_numeric($teacher->quiz_generation_limit) && is_numeric($teacher->quizzes_used)) {
            $remaining = max(0, (int) $teacher->quiz_generation_limit - (int) $teacher->quizzes_used);
        }
        if ($remaining !== null && $remaining <= 1) {
            $rows->push($this->makeRow(
                $teacher->id,
                'plan.limit.remaining_'.$remaining,
                $remaining === 0 ? 'Quiz generation limit reached' : 'You have 1 quiz generation left',
                $remaining === 0
                    ? 'Upgrade your plan to continue generating quizzes.'
                    : 'Upgrade your plan to avoid generation interruptions.',
                'plan',
                $remaining === 0 ? 'limits_reached' : 'plan_updates',
                $remaining === 0 ? 'critical' : 'warning',
                $now->copy()->subMinutes(45),
                $now
            ));
        }

        $plan = strtolower((string) ($teacher->plan ?? 'trial'));
        if (in_array($plan, ['trial', 'basic'], true)) {
            $rows->push($this->makeRow(
                $teacher->id,
                'engagement.upgrade.'.$plan,
                'Upgrade suggestion',
                'Pro and School plans unlock richer classroom and analytics features.',
                'engagement',
                'suggestions',
                'info',
                $now->copy()->subHours(2),
                $now
            ));
        }

        $classrooms = $teacher->classrooms()->withCount('students')->get(['id', 'name']);
        $hasClassrooms = $classrooms->isNotEmpty();

        if ($hasClassrooms) {
            $studentsJoinedPastWeek = DB::table('classroom_student')
                ->join('classrooms', 'classroom_student.classroom_id', '=', 'classrooms.id')
                ->where('classrooms.user_id', $teacher->id)
                ->where('classroom_student.status', 'approved')
                ->where('classroom_student.created_at', '>=', $now->copy()->subDays(7))
                ->count();

            if ($studentsJoinedPastWeek > 0) {
                $weekStart = $now->copy()->startOfWeek()->toDateString();
                $rows->push($this->makeRow(
                    $teacher->id,
                    'classroom.student_joined.week_'.$weekStart,
                    $studentsJoinedPastWeek.' student'.($studentsJoinedPastWeek === 1 ? '' : 's').' joined your classes',
                    'Classroom activity is live and ready to review.',
                    'classroom',
                    'student_joined',
                    'info',
                    $now->copy()->subHours(1),
                    $now
                ));
            }

            $submissionsPastDay = Submission::query()
                ->whereHas('assignment.classroom', function ($query) use ($teacher) {
                    $query->where('user_id', $teacher->id);
                })
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();

            if ($submissionsPastDay > 0) {
                $rows->push($this->makeRow(
                    $teacher->id,
                    'classroom.quiz_submitted.day_'.$now->toDateString(),
                    'New student submissions',
                    $submissionsPastDay.' submission'.($submissionsPastDay === 1 ? '' : 's').' detected in recent classroom assignments.',
                    'classroom',
                    'quiz_submitted',
                    'info',
                    $now->copy()->subMinutes(30),
                    $now
                ));
            }
        }

        $quizCount = $teacher->quizzes()->count();
        $classroomCount = $classrooms->count();

        $rows->push($this->makeRow(
            $teacher->id,
            'engagement.weekly_summary.'.$now->copy()->startOfWeek()->toDateString(),
            'Weekly summary available',
            sprintf(
                'You have %d quiz%s and %d classroom%s this week.',
                $quizCount,
                $quizCount === 1 ? '' : 'zes',
                $classroomCount,
                $classroomCount === 1 ? '' : 's'
            ),
            'engagement',
            'weekly_summary',
            'success',
            $now->copy()->subHours(3),
            $now
        ));

        if ($rows->isNotEmpty()) {
            TeacherNotification::query()->upsert(
                $rows->all(),
                ['user_id', 'source_key'],
                ['title', 'message', 'category', 'event_type', 'severity', 'occurred_at', 'updated_at']
            );
        }
    }

    protected function makeRow(
        int $userId,
        string $sourceKey,
        string $title,
        string $message,
        string $category,
        string $eventType,
        string $severity,
        Carbon $occurredAt,
        Carbon $now
    ): array {
        return [
            'user_id' => $userId,
            'source_key' => $sourceKey,
            'title' => $title,
            'message' => $message,
            'category' => $category,
            'event_type' => $eventType,
            'severity' => $severity,
            'occurred_at' => $occurredAt,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\TeacherNotification;
use App\Models\User;
use Illuminate\Support\Str;

class TeacherNotificationService
{
    public const FREE_FIRST_QUIZ_REMINDER_SOURCE = 'free:engagement:first-quiz-reminder';

    /**
     * Return persisted notifications that belong to the teacher.
     */
    public function syncAndFetch(User $teacher, int $perPage = 20)
    {
        $this->syncFirstQuizReminder($teacher);
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

    public function create(User $teacher, array $attributes): ?TeacherNotification
    {
        if (! $this->isFreePlanTeacher($teacher)) {
            return null;
        }

        return $teacher->teacherNotifications()->create($this->withDefaults($attributes));
    }

    public function upsertBySource(User $teacher, string $sourceKey, array $attributes): ?TeacherNotification
    {
        if (! $this->isFreePlanTeacher($teacher)) {
            return null;
        }

        $notification = $teacher->teacherNotifications()->firstOrNew([
            'source_key' => $sourceKey,
        ]);

        $payload = $this->withDefaults([
            ...$attributes,
            'source_key' => $sourceKey,
        ]);

        $notification->fill($payload);
        $notification->is_read = false;
        $notification->read_at = null;
        $notification->save();

        return $notification->fresh();
    }

    public function deleteBySource(User $teacher, string $sourceKey): void
    {
        $teacher->teacherNotifications()->where('source_key', $sourceKey)->delete();
    }

    public function syncFirstQuizReminder(User $teacher): void
    {
        if (! $this->isFreePlanTeacher($teacher)) {
            return;
        }

        if ($teacher->quizzes()->exists()) {
            $this->deleteBySource($teacher, self::FREE_FIRST_QUIZ_REMINDER_SOURCE);
            return;
        }

        $existing = $teacher->teacherNotifications()->where('source_key', self::FREE_FIRST_QUIZ_REMINDER_SOURCE)->exists();
        if ($existing) {
            return;
        }

        $teacher->teacherNotifications()->create($this->withDefaults([
            'source_key' => self::FREE_FIRST_QUIZ_REMINDER_SOURCE,
            'title' => "You haven't created your first quiz yet",
            'message' => 'Create your first quiz to start getting instant classroom-ready content.',
            'category' => 'engagement',
            'event_type' => 'suggestions',
            'severity' => 'info',
        ]));
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

    protected function isFreePlanTeacher(User $teacher): bool
    {
        $plan = strtolower((string) ($teacher->plan ?? 'free'));

        return ($teacher->role ?? '') === 'teacher' && in_array($plan, ['free', 'trial'], true);
    }

    protected function withDefaults(array $attributes): array
    {
        return [
            'source_key' => $attributes['source_key'] ?? ('free:notification:' . Str::uuid()->toString()),
            'title' => $attributes['title'] ?? 'Notification',
            'message' => $attributes['message'] ?? '',
            'category' => $attributes['category'] ?? 'system',
            'event_type' => $attributes['event_type'] ?? 'system_notice',
            'severity' => $attributes['severity'] ?? 'info',
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'is_read' => $attributes['is_read'] ?? false,
            'read_at' => $attributes['read_at'] ?? null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\TeacherNotification;
use App\Models\User;

class TeacherNotificationService
{
    /**
     * Return persisted notifications that belong to the teacher.
     */
    public function syncAndFetch(User $teacher, int $perPage = 20)
    {
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
}

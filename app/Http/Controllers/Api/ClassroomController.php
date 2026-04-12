<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'student') {
            return response()->json(
                $user->enrolledClassrooms()
                    ->withCount('students')
                    ->with('teacher:id,fullname')
                    ->latest()
                    ->get()
            );
        }

        return response()->json(
            $user->classrooms()->withCount('students')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'room' => 'nullable|string|max:255',
            'schedule' => 'nullable|string|max:255',
        ]);

        $classroom = $request->user()->classrooms()->create([
            'name' => $validated['name'],
            'room' => $validated['room'],
            'schedule' => $validated['schedule'],
            'join_code' => strtoupper(Str::random(6)),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Classroom created successfully',
            'classroom' => $classroom->loadCount('students'),
        ], 201);
    }

    public function update(Request $request, Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'room' => 'nullable|string|max:255',
            'schedule' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $classroom->update($validated);

        return response()->json([
            'message' => 'Classroom updated successfully',
            'classroom' => $classroom->loadCount('students'),
        ]);
    }

    public function show(Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $classroom->load([
            'students',
            'assignments.quiz.questions:id,quiz_id',
            'assignments.submissions' => function ($query) {
                $query->select('id', 'assignment_id', 'user_id', 'score', 'answers', 'created_at')
                    ->latest('created_at');
            },
        ]);

        $approvedStudents = $classroom->students
            ->filter(function ($student) {
                return ($student->pivot?->status ?? 'approved') === 'approved';
            })
            ->values();

        $assignments = $classroom->assignments
            ->map(function ($assignment) use ($approvedStudents) {
                $questionCount = (int) ($assignment->quiz?->questions?->count() ?? 0);
                $submissionsByStudentId = $assignment->submissions->keyBy('user_id');

                $studentExamStatuses = $approvedStudents
                    ->map(function ($student) use ($assignment, $submissionsByStudentId, $questionCount) {
                        $submission = $submissionsByStudentId->get($student->id);

                        if ($submission) {
                            $answers = is_array($submission->answers) ? $submission->answers : [];
                            $gradedItems = count($answers);
                            $answeredItems = collect($answers)->filter(function ($entry) {
                                if (!is_array($entry)) {
                                    return trim((string) $entry) !== '';
                                }

                                return trim((string) ($entry['answer'] ?? '')) !== '';
                            })->count();

                            $completionRate = $gradedItems > 0
                                ? round(($answeredItems / $gradedItems) * 100, 1)
                                : null;
                            $score = is_numeric($submission->score) ? (float) $submission->score : null;
                            $needsAttention = ($completionRate !== null && $completionRate < 60.0)
                                || ($score !== null && $score < 40.0);

                            return [
                                'student_id' => $student->id,
                                'student_name' => $student->fullname,
                                'student_email' => $student->email,
                                'status' => $needsAttention ? 'needs_attention' : 'submitted',
                                'submission_id' => $submission->id,
                                'submitted_at' => $submission->created_at,
                                'score' => $score,
                                'completion_rate' => $completionRate,
                                'answered_items' => $answeredItems,
                                'graded_items' => $gradedItems,
                            ];
                        }

                        $isOverdue = $assignment->deadline_at
                            ? now()->greaterThan($assignment->deadline_at)
                            : false;

                        return [
                            'student_id' => $student->id,
                            'student_name' => $student->fullname,
                            'student_email' => $student->email,
                            'status' => $isOverdue ? 'not_taken' : 'in_progress',
                            'submission_id' => null,
                            'submitted_at' => null,
                            'score' => null,
                            'completion_rate' => null,
                            'answered_items' => null,
                            'graded_items' => $questionCount > 0 ? $questionCount : null,
                        ];
                    })
                    ->values();

                $statusCounts = [
                    'submitted' => 0,
                    'not_taken' => 0,
                    'in_progress' => 0,
                    'needs_attention' => 0,
                ];

                foreach ($studentExamStatuses as $studentStatus) {
                    $status = (string) ($studentStatus['status'] ?? '');
                    if (array_key_exists($status, $statusCounts)) {
                        $statusCounts[$status]++;
                    }
                }

                return [
                    'id' => $assignment->id,
                    'classroom_id' => $assignment->classroom_id,
                    'deadline_at' => $assignment->deadline_at,
                    'created_at' => $assignment->created_at,
                    'quiz' => $assignment->quiz
                        ? [
                            'id' => $assignment->quiz->id,
                            'title' => $assignment->quiz->title,
                            'topic' => $assignment->quiz->topic,
                            'question_count' => $questionCount,
                        ]
                        : null,
                    'status_counts' => $statusCounts,
                    'student_exam_statuses' => $studentExamStatuses,
                ];
            })
            ->values();

        $students = $classroom->students->map(function ($student) {
            return [
                'id' => $student->id,
                'fullname' => $student->fullname,
                'email' => $student->email,
                'created_at' => $student->created_at,
                'pivot' => [
                    'created_at' => $student->pivot?->created_at,
                    'updated_at' => $student->pivot?->updated_at,
                    'status' => $student->pivot?->status,
                ],
            ];
        })->values();

        return response()->json([
            'id' => $classroom->id,
            'name' => $classroom->name,
            'join_code' => $classroom->join_code,
            'room' => $classroom->room,
            'schedule' => $classroom->schedule,
            'is_active' => $classroom->is_active,
            'students' => $students,
            'assignments' => $assignments,
        ]);
    }

    public function destroy(Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $classroom->delete();

        return response()->json([
            'message' => 'Classroom deleted successfully',
        ]);
    }

    public function updateInviteExpiration(Request $request, Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'expires_at' => 'nullable|date|after:now',
        ]);

        $classroom->update([
            'invite_expires_at' => $validated['expires_at'],
        ]);

        return response()->json([
            'message' => 'Invite expiration updated successfully',
            'invite_expires_at' => $classroom->invite_expires_at,
        ]);
    }

    public function joinByCode(Request $request)
    {
        $request->merge([
            'join_code' => strtoupper(trim((string) $request->input('join_code'))),
        ]);

        $validated = $request->validate([
            'join_code' => 'required|string|size:6',
        ]);

        $classroom = Classroom::where('join_code', $validated['join_code'])->first();

        if (!$classroom) {
            return response()->json(['message' => 'Invalid classroom code. Please check and try again.'], 404);
        }

        if (!$classroom->is_active) {
            return response()->json(['message' => 'This classroom is no longer active.'], 403);
        }

        if ($classroom->invite_expires_at && now()->isAfter($classroom->invite_expires_at)) {
            return response()->json(['message' => 'This invitation link has expired. Please contact your teacher.'], 403);
        }

        $user = $request->user();

        $existingEnrollment = $user->enrolledClassrooms()
            ->where('classroom_id', $classroom->id)
            ->first();

        if ($existingEnrollment) {
            if ($existingEnrollment->pivot?->status === 'approved') {
                return response()->json(['message' => 'You are already enrolled in this classroom.'], 409);
            }

            if ($existingEnrollment->pivot?->status === 'pending') {
                return response()->json(['message' => 'Your enrollment request is already pending teacher approval.'], 409);
            }

            $user->enrolledClassrooms()->updateExistingPivot($classroom->id, [
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => "Enrollment request re-submitted for {$classroom->name}. Waiting for teacher approval.",
                'classroom' => $classroom->loadCount('students'),
            ]);
        }

        $user->enrolledClassrooms()->attach($classroom->id, [
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => "Enrollment request submitted for {$classroom->name}. Waiting for teacher approval.",
            'classroom' => $classroom->loadCount('students'),
        ]);
    }

    public function approveStudent(Request $request, Classroom $classroom, User $student)
    {
        if ($request->user()->id !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Selected user is not a student.'], 422);
        }

        if (! $classroom->students()->where('users.id', $student->id)->exists()) {
            return response()->json(['message' => 'Student is not linked to this classroom.'], 404);
        }

        $classroom->students()->updateExistingPivot($student->id, [
            'status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Student enrollment approved successfully.',
        ]);
    }

    public function rejectStudent(Request $request, Classroom $classroom, User $student)
    {
        if ($request->user()->id !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Selected user is not a student.'], 422);
        }

        if (! $classroom->students()->where('users.id', $student->id)->exists()) {
            return response()->json(['message' => 'Student is not linked to this classroom.'], 404);
        }

        $classroom->students()->updateExistingPivot($student->id, [
            'status' => 'rejected',
        ]);

        return response()->json([
            'message' => 'Student enrollment request rejected.',
        ]);
    }

    public function updateStudentStatus(Request $request, Classroom $classroom, User $student)
    {
        if ($request->user()->id !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($student->role !== 'student') {
            return response()->json(['message' => 'Selected user is not a student.'], 422);
        }

        if (! $classroom->students()->where('users.id', $student->id)->exists()) {
            return response()->json(['message' => 'Student is not linked to this classroom.'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,approved,suspended,rejected',
        ]);

        $classroom->students()->updateExistingPivot($student->id, [
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Student status updated successfully.',
            'status' => $validated['status'],
        ]);
    }
}

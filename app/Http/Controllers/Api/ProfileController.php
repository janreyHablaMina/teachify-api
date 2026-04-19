<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeacherNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly TeacherNotificationService $notificationService)
    {
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            // Identity
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'school' => ['nullable', 'string', 'max:255'],
            'subjects' => ['nullable', 'array'],
            'teaching_level' => ['nullable', 'string', 'max:255'],
            // AI Preferences
            'ai_default_difficulty' => ['nullable', 'string', 'in:easy,medium,hard'],
            'ai_default_question_type' => ['nullable', 'string'],
            'ai_language' => ['nullable', 'string', 'max:100'],
            'ai_tone' => ['nullable', 'string', 'max:100'],
            'ai_generate_explanations' => ['nullable', 'boolean'],
            'ai_include_rationale' => ['nullable', 'boolean'],
            // Notifications
            'notify_email' => ['nullable', 'boolean'],
            'notify_quiz_completed' => ['nullable', 'boolean'],
            'notify_student_submission' => ['nullable', 'boolean'],
            'notify_weekly_summary' => ['nullable', 'boolean'],
            // UI
            'ui_theme' => ['nullable', 'string', 'in:light,dark'],
            'ui_accent_color' => ['nullable', 'string', 'max:20'],
            'ui_density' => ['nullable', 'string', 'in:comfortable,compact'],
            'two_factor_enabled' => ['nullable', 'boolean'],
        ]);

        $user->update($validated);

        $this->notificationService->upsertBySource($user, 'free:system:profile-updated', [
            'title' => 'Profile updated successfully',
            'message' => 'Your profile details were saved.',
            'category' => 'system',
            'event_type' => 'system_notice',
            'severity' => 'success',
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $this->notificationService->upsertBySource($user, 'free:system:password-changed', [
            'title' => 'Password changed',
            'message' => 'Your account password was updated successfully.',
            'category' => 'system',
            'event_type' => 'system_notice',
            'severity' => 'success',
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Update the user's avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = $request->user();

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('profile-photos', 'public');
            
            $user->update([
                'profile_photo_path' => $path,
            ]);
        }

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'profile_photo_url' => $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null,
        ]);
    }
}

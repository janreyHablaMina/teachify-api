<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_plan_has_increased_limits(): void
    {
        $user = User::factory()->create([
            'plan' => 'pro',
            'quiz_generation_limit' => 3, // Initial default
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.quiz_generation_limit', 200)
            ->assertJsonPath('user.max_questions_per_quiz', 50);
    }

    public function test_school_plan_has_increased_limits(): void
    {
        $user = User::factory()->create([
            'plan' => 'school',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.quiz_generation_limit', 1000)
            ->assertJsonPath('user.max_questions_per_quiz', 50);
    }

    public function test_basic_plan_has_correct_limits(): void
    {
        $user = User::factory()->create([
            'plan' => 'basic',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.quiz_generation_limit', 50)
            ->assertJsonPath('user.max_questions_per_quiz', 50);
    }
}

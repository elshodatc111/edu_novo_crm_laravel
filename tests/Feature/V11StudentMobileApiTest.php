<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V11StudentMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_see_own_profile(): void
    {
        $branch = $this->branch();
        $student = $this->student($branch, ['name' => 'Nodira', 'phone' => '+998 90 123 4567']);
        $token = $student->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->getJson('/api/v1/me/profile');

        $response->assertOk()->assertJsonPath('data.name', 'Nodira')->assertJsonPath('data.role', 'student');
    }

    public function test_student_sees_all_own_groups_in_one_list(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.view', 'groups.members', 'groups.create']);
        $this->actingAs($admin);
        $catalog = $this->catalog($branch);
        $group = app(\App\Services\GroupService::class)->create($this->groupPayload($catalog), $admin);

        $student = $this->student($branch);
        app(\App\Services\EnrollmentService::class)->enroll($group, $student, null, $admin);

        $token = $student->createToken('mobile')->plainTextToken;
        $response = $this->api($token)->getJson('/api/v1/me/groups');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($group->id, $response->json('data.0.id'));
        $this->assertTrue($response->json('data.0.is_active_member'));
    }

    public function test_student_attendance_history_summarizes_all_groups(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.view', 'groups.members', 'groups.create', 'attendance.take']);
        $this->actingAs($admin);
        $catalog = $this->catalog($branch);
        $group = app(\App\Services\GroupService::class)->create($this->groupPayload($catalog, ['starts_on' => today()->toDateString()]), $admin);

        $student = $this->student($branch);
        app(\App\Services\EnrollmentService::class)->enroll($group, $student, null, $admin);

        $token = $student->createToken('mobile')->plainTextToken;
        $response = $this->api($token)->getJson('/api/v1/me/attendance');

        $response->assertOk()->assertJsonStructure(['data' => ['groups', 'overall_rate']]);
    }

    public function test_staff_cannot_access_student_only_endpoints(): void
    {
        $branch = $this->branch();
        $teacher = $this->user(Role::Teacher, $branch);
        $token = $teacher->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson('/api/v1/me/groups')->assertForbidden();
        $this->api($token)->getJson('/api/v1/me/attendance')->assertForbidden();
    }
}

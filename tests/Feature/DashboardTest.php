<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // create minimal permissions & roles used by dashboard
        Permission::firstOrCreate(['name' => 'view schedule']);
        Permission::firstOrCreate(['name' => 'generate schedule']);
        Permission::firstOrCreate(['name' => 'import data']);
        Permission::firstOrCreate(['name' => 'export schedule']);
        Permission::firstOrCreate(['name' => 'manage students']);
        Permission::firstOrCreate(['name' => 'manage teachers']);
        Permission::firstOrCreate(['name' => 'manage courses']);

        Role::firstOrCreate(['name' => 'admin'])->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'scheduler'])->syncPermissions([
            'import data',
            'generate schedule',
            'export schedule',
            'view schedule',
        ]);
        Role::firstOrCreate(['name' => 'teacher'])->syncPermissions(['view schedule']);
        Role::firstOrCreate(['name' => 'student'])->syncPermissions(['view schedule']);
    }

    public function test_admin_dashboard_includes_global_stats_and_role()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('admin', $data['role']);
        $this->assertArrayHasKey('total_students', $data['stats']);
        $this->assertArrayHasKey('total_teachers', $data['stats']);
        $this->assertArrayHasKey('total_courses', $data['stats']);
        $this->assertArrayHasKey('total_schedules', $data['stats']);
    }

    public function test_scheduler_dashboard_shows_only_schedule_count()
    {
        $user = User::factory()->create();
        $user->assignRole('scheduler');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('scheduler', $data['role']);
        $this->assertArrayHasKey('total_schedules', $data['stats']);
        $this->assertCount(1, $data['stats']);
    }

    public function test_teacher_dashboard_hides_stats()
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('teacher', $data['role']);
        $this->assertEmpty($data['stats']);
    }
}

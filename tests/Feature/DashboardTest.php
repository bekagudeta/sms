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

        foreach (\Database\Seeders\RolesAndPermissionsSeeder::schedulerPermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Permission::firstOrCreate(['name' => 'manage students']);
        Permission::firstOrCreate(['name' => 'manage teachers']);
        Permission::firstOrCreate(['name' => 'manage courses']);

        Role::firstOrCreate(['name' => 'admin'])->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'scheduler'])->syncPermissions(
            \Database\Seeders\RolesAndPermissionsSeeder::schedulerPermissions()
        );
        Role::firstOrCreate(['name' => 'teacher'])->syncPermissions(['view schedule']);
        Role::firstOrCreate(['name' => 'student'])->syncPermissions(['view schedule']);
    }

    public function test_admin_dashboard_includes_global_stats_and_role()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app('App\\Http\\Middleware\\HandleInertiaRequests')->version(request()),
            ])
            ->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('admin', $data['role']);
        $this->assertArrayHasKey('total_students', $data['stats']);
        $this->assertArrayHasKey('total_teachers', $data['stats']);
        $this->assertArrayHasKey('total_courses', $data['stats']);
        $this->assertArrayHasKey('total_schedules', $data['stats']);
    }

    public function test_scheduler_dashboard_shows_scheduling_stats_only()
    {
        $user = User::factory()->create();
        $user->assignRole('scheduler');

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app('App\\Http\\Middleware\\HandleInertiaRequests')->version(request()),
            ])
            ->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('scheduler', $data['role']);
        $this->assertArrayHasKey('total_schedules', $data['stats']);
        $this->assertArrayHasKey('total_sections', $data['stats']);
        $this->assertArrayHasKey('unscheduled_sections', $data['stats']);
        $this->assertCount(3, $data['stats']);
        $this->assertArrayNotHasKey('total_students', $data['stats']);
    }

    public function test_scheduler_has_entity_view_permissions_for_sidebar()
    {
        $user = User::factory()->create();
        $user->assignRole('scheduler');

        $this->assertTrue($user->can('view semesters'));
        $this->assertTrue($user->can('view departments'));
        $this->assertTrue($user->can('view enrollments'));
        $this->assertTrue($user->can('view section-teachers'));
        $this->assertTrue($user->can('view students'));
        $this->assertTrue($user->can('view courses'));
    }

    public function test_teacher_dashboard_hides_stats()
    {
        $user = User::factory()->create();
        $user->assignRole('teacher');

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app('App\\Http\\Middleware\\HandleInertiaRequests')->version(request()),
            ])
            ->get('/dashboard');
        $response->assertOk();

        $data = $response->json('props');
        $this->assertEquals('teacher', $data['role']);
        $this->assertEmpty($data['stats']);
    }
}

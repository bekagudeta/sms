<?php

namespace Tests\Unit;

use App\Imports\TeachersImport;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_department_with_numeric_id()
    {
        $department = Department::create(['code' => 'CS', 'name' => 'Computer Science']);

        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = ['department_id' => (string) $department->id];
        $result = $method->invoke($import, $row);

        $this->assertEquals($department->id, $result->id);
    }

    public function test_resolve_department_with_code_in_id_field()
    {
        $department = Department::create(['code' => 'MATH', 'name' => 'Mathematics']);

        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = ['department_id' => 'MATH'];
        $result = $method->invoke($import, $row);

        $this->assertEquals($department->id, $result->id);
    }

    public function test_resolve_department_with_department_code()
    {
        $department = Department::create(['code' => 'ENG', 'name' => 'English']);

        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = ['department_code' => 'ENG'];
        $result = $method->invoke($import, $row);

        $this->assertEquals($department->id, $result->id);
    }

    public function test_resolve_department_with_department_name()
    {
        $department = Department::create(['code' => 'PHY', 'name' => 'Physics']);

        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = ['department_name' => 'Physics'];
        $result = $method->invoke($import, $row);

        $this->assertEquals($department->id, $result->id);
    }

    public function test_resolve_department_with_invalid_id_fails()
    {
        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = ['department_id' => '999'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Import failed: department_id '999' not found for teacher row.");

        $method->invoke($import, $row);
    }

    public function test_resolve_department_with_collection()
    {
        $department = Department::create(['code' => 'BIO', 'name' => 'Biology']);

        $import = new TeachersImport();
        $reflection = new \ReflectionClass($import);
        $method = $reflection->getMethod('resolveDepartment');
        $method->setAccessible(true);

        $row = new Collection(['department_id' => (string) $department->id]);
        $result = $method->invoke($import, $row);

        $this->assertEquals($department->id, $result->id);
    }
}
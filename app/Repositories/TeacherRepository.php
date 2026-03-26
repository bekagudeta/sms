<?php



namespace App\Repositories;



use App\Models\Teacher;

use Illuminate\Support\Collection;



class TeacherRepository

{

    public function getAll(): Collection

    {

        return Teacher::with('department')->get();
    }

    public function paginate(int $perPage = 10, ?string $search = null)
    {
        return Teacher::with('department')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('teacher_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }




    public function findById($id): ?Teacher

    {

        return Teacher::with(['department', 'sections.courseOffering.course'])
            ->find($id);

    }



    public function findByTeacherId($teacherId): ?Teacher

    {

        return Teacher::where('teacher_id', $teacherId)->first();

    }



    public function create(array $data): Teacher

    {

        return Teacher::create($data);

    }



    public function update($id, array $data): bool

    {

        $teacher = Teacher::find($id);

        return $teacher ? $teacher->update($data) : false;

    }



    public function delete($id): bool

    {

        $teacher = $this->findById($id);

        if (! $teacher) {
            return false;
        }

        // detach related sections to avoid foreign key issues on pivot
        $teacher->sections()->detach();

        return $teacher->delete();

    }



    public function getByDepartment($departmentId): Collection

    {

        return Teacher::where('department_id', $departmentId)

                     ->with('department')

                     ->get();

    }



    public function getAvailableTeachers($maxHours = 20): Collection

    {

        return Teacher::where('max_hours_per_week', '>=', $maxHours)

                     ->with('department')

                     ->get();

    }

}

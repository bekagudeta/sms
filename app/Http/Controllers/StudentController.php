<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Department;
use App\Repositories\StudentRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    protected $repository;

    public function __construct(StudentRepository $repository)
    {
        $this->repository = $repository;
        $this->authorizeResource(Student::class, 'student');
    }

    public function index(Request $request)
    {
        $students = $this->repository->paginate(
            config('app.pagination', 10),
            $request->input('search')
        );

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $departments = Department::select('id', 'name')->get();

        return Inertia::render('Students/Create', [
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id'      => 'required|string|max:50|unique:students,student_id',
            'user_id'         => 'required|exists:users,id',
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email|max:255|unique:students,email',
            'phone'           => 'nullable|string|max:20',
            'department_id'   => 'required|exists:departments,id',
            'status'          => 'nullable|string|in:active,inactive,pending,graduated,suspended',
            'enrollment_date' => 'nullable|date',
        ]);

        $this->repository->create($validated);

        return redirect()->route('entities.index', ['entityType' => 'students'])
            ->with('success', 'Student created successfully.');
    }

    public function edit(Student $student)
    {
        $student->load('department'); // prevent future N+1

        $departments = Department::select('id', 'name')->get();

        return Inertia::render('Students/Edit', [
            'student' => $student,
            'departments' => $departments
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id'      => 'required|string|max:50|unique:students,student_id,' . $student->id,
            'user_id'         => 'required|exists:users,id',
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email|max:255|unique:students,email,' . $student->id,
            'phone'           => 'nullable|string|max:20',
            'department_id'   => 'required|exists:departments,id',
            'status'          => 'nullable|string|in:active,inactive,pending,graduated,suspended',
            'enrollment_date' => 'nullable|date',
        ]);

        $this->repository->update($student->id, $validated);

        return redirect()->route('entities.index', ['entityType' => 'students'])
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        $this->repository->delete($student->id);

        return redirect()->route('entities.index', ['entityType' => 'students'])
            ->with('success', 'Student deleted successfully.');
    }
}
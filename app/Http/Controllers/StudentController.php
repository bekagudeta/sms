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
        $students = $this->repository->paginate(10, $request->input('search'));
        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $departments = Department::all();
        return Inertia::render('Students/Create', [
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|unique:students',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:students',
            'phone' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'semester' => 'required|integer|min:1|max:12',
            'enrollment_date' => 'required|date'
        ]);

        $this->repository->create($validated);

        return redirect()->route('students.index')
            ->with('success', 'Student created successfully.');
    }

    public function edit(Student $student)
    {
        $departments = Department::all();
        return Inertia::render('Students/Edit', [
            'student' => $student,
            'departments' => $departments
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'semester' => 'required|integer|min:1|max:12',
        ]);

        $this->repository->update($student->id, $validated);

        return redirect()->route('students.index')
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        $this->repository->delete($student->id);

        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }
}
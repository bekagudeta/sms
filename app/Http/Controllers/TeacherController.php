<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\Department;
use App\Repositories\TeacherRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherController extends Controller
{
    protected $repository;

    public function __construct(TeacherRepository $repository)
    {
        $this->repository = $repository;
        $this->authorizeResource(Teacher::class, 'teacher');
    }

    public function index(Request $request)
    {
        $teachers = $this->repository->paginate(10, $request->input('search'));
        return Inertia::render('Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $departments = Department::all();
        return Inertia::render('Teachers/Create', [
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|string|unique:teachers',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:teachers',
            'phone' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'qualification' => 'nullable|string',
            'max_hours_per_week' => 'required|integer|min:1|max:40'
        ]);

        $this->repository->create($validated);

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher created successfully.');
    }

    public function edit(Teacher $teacher)
    {
        $departments = Department::all();
        return Inertia::render('Teachers/Edit', [
            'teacher' => $teacher,
            'departments' => $departments
        ]);
    }

    public function update(Request $request, Teacher $teacher)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:teachers,email,' . $teacher->id,
            'phone' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'qualification' => 'nullable|string',
            'max_hours_per_week' => 'required|integer|min:1|max:40'
        ]);

        $this->repository->update($teacher->id, $validated);

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher updated successfully.');
    }

    public function destroy(Teacher $teacher)
    {
        $this->repository->delete($teacher->id);

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted successfully.');
    }
}
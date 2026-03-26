<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\Department;
use App\Repositories\TeacherRepository;
use App\Http\Requests\TeacherRequest;
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
        $teachers = $this->repository->paginate(
            config('app.pagination', 10),
            $request->input('search')
        );

        return Inertia::render('Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $departments = Department::select('id', 'name')->get();

        return Inertia::render('Teachers/Create', [
            'departments' => $departments
        ]);
    }

    public function store(TeacherRequest $request)
    {
        $this->repository->create($request->validated());

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher created successfully.');
    }

    public function edit(Teacher $teacher)
    {
        $teacher->load('department');

        $departments = Department::select('id', 'name')->get();

        return Inertia::render('Teachers/Edit', [
            'teacher' => $teacher,
            'departments' => $departments
        ]);
    }

    public function update(TeacherRequest $request, Teacher $teacher)
    {
        $this->repository->update($teacher->id, $request->validated());

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
<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Teacher;
use App\Repositories\CourseRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController extends Controller
{
    protected $repository;

    public function __construct(CourseRepository $repository)
    {
        $this->repository = $repository;
        $this->authorizeResource(Course::class, 'course');
    }

    public function index(Request $request)
    {
        $courses = $this->repository->paginate(10, $request->input('search'));
        return Inertia::render('Courses/Index', [
            'courses' => $courses,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        $departments = Department::all();
        
        return Inertia::render('Courses/Create', [
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_code' => 'required|string|unique:courses',
            'course_name' => 'required|string',
            'description' => 'nullable|string',
            'credits' => 'required|integer|min:1|max:6',
            'hours_per_week' => 'required|integer|min:1|max:6',
            'department_id' => 'required|exists:departments,id',
            'level' => 'required|in:undergraduate,graduate,diploma'
        ]);

        $this->repository->create($validated);

        return redirect()->route('courses.index')
            ->with('success', 'Course created successfully.');
    }

    public function edit(Course $course)
    {
        $departments = Department::all();
        
        return Inertia::render('Courses/Edit', [
            'course' => $course,
            'departments' => $departments
        ]);
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'course_code' => 'required|string|unique:courses,course_code,' . $course->id,
            'course_name' => 'required|string',
            'description' => 'nullable|string',
            'credits' => 'required|integer|min:1|max:6',
            'hours_per_week' => 'required|integer|min:1|max:6',
            'department_id' => 'required|exists:departments,id',
            'level' => 'required|in:undergraduate,graduate,diploma'
        ]);

        $this->repository->update($course->id, $validated);

        return redirect()->route('courses.index')
            ->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        $this->repository->delete($course->id);

        return redirect()->route('courses.index')
            ->with('success', 'Course deleted successfully.');
    }
}
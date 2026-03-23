<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use App\Models\Course;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseOfferingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(CourseOffering::class, 'courseOffering');
    }

    public function index(Request $request)
    {
        $courseOfferings = CourseOffering::with(['course', 'semester'])
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->search, function($q) use ($request) {
                $q->whereHas('course', fn($cq) => $cq->where('course_name', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('course', fn($cq) => $cq->where('course_code', 'like', '%' . $request->search . '%'));
            })
            ->paginate(10);

        $semesters = Semester::all();

        return Inertia::render('CourseOfferings/Index', [
            'courseOfferings' => $courseOfferings,
            'semesters' => $semesters,
            'filters' => $request->only(['semester_id', 'search'])
        ]);
    }

    public function create()
    {
        $courses = Course::all();
        $semesters = Semester::all();

        return Inertia::render('CourseOfferings/Create', [
            'courses' => $courses,
            'semesters' => $semesters
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
            'expected_students' => 'required|integer|min:1'
        ]);

        // Check if offering already exists
        $exists = CourseOffering::where('course_id', $validated['course_id'])
            ->where('semester_id', $validated['semester_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['course_id' => 'This course is already offered in the selected semester.']);
        }

        CourseOffering::create($validated);

        return redirect()->route('course-offerings.index')
            ->with('success', 'Course offering created successfully.');
    }

    public function show(CourseOffering $courseOffering)
    {
        $courseOffering->load(['course', 'semester', 'sections.teachers', 'sections.schedules.room', 'sections.schedules.timeslot']);

        return Inertia::render('CourseOfferings/Show', [
            'courseOffering' => $courseOffering
        ]);
    }

    public function edit(CourseOffering $courseOffering)
    {
        $courses = Course::all();
        $semesters = Semester::all();

        return Inertia::render('CourseOfferings/Edit', [
            'courseOffering' => $courseOffering,
            'courses' => $courses,
            'semesters' => $semesters
        ]);
    }

    public function update(Request $request, CourseOffering $courseOffering)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
            'expected_students' => 'required|integer|min:1'
        ]);

        // Check if offering already exists (excluding current)
        $exists = CourseOffering::where('course_id', $validated['course_id'])
            ->where('semester_id', $validated['semester_id'])
            ->where('id', '!=', $courseOffering->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['course_id' => 'This course is already offered in the selected semester.']);
        }

        $courseOffering->update($validated);

        return redirect()->route('course-offerings.index')
            ->with('success', 'Course offering updated successfully.');
    }

    public function destroy(CourseOffering $courseOffering)
    {
        $courseOffering->delete();

        return redirect()->route('course-offerings.index')
            ->with('success', 'Course offering deleted successfully.');
    }
}
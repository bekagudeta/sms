<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\CourseOffering;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SectionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Section::class, 'section');
    }

    public function index(Request $request)
    {
        $sections = Section::with(['courseOffering.course', 'courseOffering.semester', 'teachers', 'schedules.room', 'schedules.timeslot'])
            ->when($request->course_offering_id, fn($q) => $q->where('course_offering_id', $request->course_offering_id))
            ->when($request->semester_id, function($q) use ($request) {
                $q->whereHas('courseOffering', fn($cq) => $cq->where('semester_id', $request->semester_id));
            })
            ->paginate(10);

        $courseOfferings = CourseOffering::with(['course', 'semester'])->get();
        $semesters = \App\Models\Semester::all();

        return Inertia::render('Sections/Index', [
            'sections' => $sections,
            'courseOfferings' => $courseOfferings,
            'semesters' => $semesters,
            'filters' => $request->only(['course_offering_id', 'semester_id'])
        ]);
    }

    public function create(Request $request)
    {
        $courseOfferings = CourseOffering::with(['course', 'semester'])
            ->when($request->course_offering_id, fn($q) => $q->where('id', $request->course_offering_id))
            ->get();
        $teachers = Teacher::with('department')->get();

        return Inertia::render('Sections/Create', [
            'courseOfferings' => $courseOfferings,
            'teachers' => $teachers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'section_name' => 'required|string|max:10',
            'capacity' => 'required|integer|min:1',
            'teacher_ids' => 'array',
            'teacher_ids.*' => 'exists:teachers,id'
        ]);

        $section = Section::create([
            'course_offering_id' => $validated['course_offering_id'],
            'section_name' => $validated['section_name'],
            'capacity' => $validated['capacity']
        ]);

        if (!empty($validated['teacher_ids'])) {
            $section->teachers()->attach($validated['teacher_ids']);
        }

        return redirect()->route('sections.index')
            ->with('success', 'Section created successfully.');
    }

    public function show(Section $section)
    {
        $section->load(['courseOffering.course', 'courseOffering.semester', 'teachers', 'schedules.room', 'schedules.timeslot', 'enrollments.student.user']);

        return Inertia::render('Sections/Show', [
            'section' => $section
        ]);
    }

    public function edit(Section $section)
    {
        $courseOfferings = CourseOffering::with(['course', 'semester'])->get();
        $teachers = Teacher::with('department')->get();

        return Inertia::render('Sections/Edit', [
            'section' => $section->load(['courseOffering', 'teachers']),
            'courseOfferings' => $courseOfferings,
            'teachers' => $teachers
        ]);
    }

    public function update(Request $request, Section $section)
    {
        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'section_name' => 'required|string|max:10',
            'capacity' => 'required|integer|min:1',
            'teacher_ids' => 'array',
            'teacher_ids.*' => 'exists:teachers,id'
        ]);

        $section->update([
            'course_offering_id' => $validated['course_offering_id'],
            'section_name' => $validated['section_name'],
            'capacity' => $validated['capacity']
        ]);

        $section->teachers()->sync($validated['teacher_ids'] ?? []);

        return redirect()->route('sections.index')
            ->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section)
    {
        $section->delete();

        return redirect()->route('sections.index')
            ->with('success', 'Section deleted successfully.');
    }
}
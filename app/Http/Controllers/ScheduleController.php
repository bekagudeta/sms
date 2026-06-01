<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualScheduleRequest;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\Timeslot;
use App\Repositories\ScheduleRepository;
use App\Services\AutoSchedulerService;
use App\Services\ScheduleAssignmentService;
use App\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    protected $schedulingService;

    protected $repository;

    protected $autoSchedulerService;

    protected $scheduleAssignmentService;

    public function __construct(
        SchedulingService $schedulingService,
        ScheduleRepository $repository,
        AutoSchedulerService $autoSchedulerService,
        ScheduleAssignmentService $scheduleAssignmentService
    ) {
        $this->schedulingService = $schedulingService;
        $this->repository = $repository;
        $this->autoSchedulerService = $autoSchedulerService;
        $this->scheduleAssignmentService = $scheduleAssignmentService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        // Teachers should only see schedules assigned to them.
        if ($role === 'teacher') {
            $teacher = $user->teacher;
            $schedules = $teacher
                ? $this->repository->paginateByTeacher($teacher->id, 10)
                : new LengthAwarePaginator([], 0, 10, $request->input('page', 1));
        }
        // Students should only see schedules for their enrolled sections.
        elseif ($role === 'student') {
            $student = $user->student;
            $schedules = new LengthAwarePaginator([], 0, 10, $request->input('page', 1));

            if ($student) {
                $schedules = $this->repository->paginateByStudent($student->id, 10);
            }
        }
        // Admins/schedulers can view full schedule list (optional semester filter).
        else {
            $semesterId = $request->input('semester_id');
            $schedules = $this->repository->paginate(10, $semesterId);
        }

        $semesters = Semester::all();

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
            'semesters' => $semesters,
            'currentSemester' => $request->input('semester_id'),
        ]);
    }

    public function showGenerateForm()
    {
        $courseOfferings = CourseOffering::with(['course', 'semester', 'sections.teachers'])->get();
        $teachers = Teacher::with('department')->get();
        $rooms = Room::all();
        $semesters = Semester::all();
        $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();
        $sections = Section::with(['courseOffering.course', 'courseOffering.semester', 'teachers', 'enrollments'])->get();

        // Debug: Check first section data
        $firstSection = $sections->first();
        Log::info('First section data:', [
            'section_id' => $firstSection?->id,
            'section_name' => $firstSection?->section_name,
            'course_offering_id' => $firstSection?->course_offering_id,
            'course_offering' => $firstSection?->courseOffering?->toArray(),
            'course' => $firstSection?->courseOffering?->course?->toArray(),
        ]);

        return Inertia::render('Schedules/Generate', [
            'courseOfferings' => $courseOfferings,
            'teachers' => $teachers,
            'rooms' => $rooms,
            'semesters' => $semesters,
            'timeslots' => $timeslots,
            'sections' => $sections,
        ]);
    }

    public function generate(ManualScheduleRequest $request)
    {
        try {
            $semesterModel = Semester::find($request->semesterId());
            $scheduleItems = $request->scheduleItems();

            if (! $semesterModel) {
                return back()->with('error', 'Invalid semester selected.');
            }

            DB::beginTransaction();

            try {
                $createdCount = 0;
                $errors = [];

                foreach ($scheduleItems as $index => $item) {
                    $result = $this->scheduleAssignmentService->createManualAssignment($item, $semesterModel);

                    if ($result['success']) {
                        $createdCount++;
                    } else {
                        $errors[] = 'Item '.($index + 1).': '.$result['message'];
                    }
                }

                if ($createdCount > 0) {
                    DB::commit();

                    $message = "Successfully generated {$createdCount} schedule(s) for {$semesterModel->name}.";
                    if (! empty($errors)) {
                        $message .= ' Some items had errors: '.implode('; ', $errors);
                    }

                    return redirect()->route('schedules.index')
                        ->with('success', $message);
                } else {
                    DB::rollBack();

                    return back()->with('error', 'No schedules were created. '.implode('; ', $errors));
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Manual schedule generation failed', [
                'error' => $e->getMessage(),
                'semester_id' => $request->input('data.semester_id'),
            ]);

            return back()->with('error', 'An error occurred while generating schedules.');
        }
    }

    /**
     * Validate schedule input data
     */
    private function validateScheduleData($semester, $scheduleItems): array
    {
        if (empty($semester)) {
            return ['valid' => false, 'message' => 'Please select a semester.'];
        }

        if (empty($scheduleItems) || ! is_array($scheduleItems)) {
            return ['valid' => false, 'message' => 'Please add at least one schedule item.'];
        }

        // Validate each schedule item has required fields (manual section-based scheduling)
        foreach ($scheduleItems as $index => $item) {
            $requiredFields = ['course_offering_id', 'section_id', 'teacher_id', 'room_id', 'timeslot_id'];
            foreach ($requiredFields as $field) {
                if (empty($item[$field])) {
                    return ['valid' => false, 'message' => 'Schedule item '.($index + 1)." is missing required field: $field"];
                }
            }
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * Find semester by name with flexible matching
     */
    private function findSemesterByName($semester): ?Semester
    {
        $suffixes = ['st', 'nd', 'rd', 'th'];

        foreach ($suffixes as $suffix) {
            $semesterModel = Semester::where('name', $semester.$suffix.' Semester')->first();
            if ($semesterModel) {
                return $semesterModel;
            }
        }

        return null;
    }

    /**
     * Create a single schedule item with proper validation
     */
    private function createScheduleItem(array $item, Semester $semester): array
    {
        try {
            Log::info('Creating schedule item:', ['item' => $item]);

            // Find course offering
            $courseOffering = CourseOffering::find($item['course_offering_id'] ?? null);
            if (! $courseOffering) {
                Log::error('Course offering not found:', ['course_offering_id' => $item['course_offering_id'] ?? null]);

                return ['success' => false, 'message' => 'Course offering not found'];
            }

            // Find teacher and validate assignment
            $teacher = Teacher::find($item['teacher_id'] ?? null);
            if (! $teacher) {
                Log::error('Teacher not found:', ['teacher_id' => $item['teacher_id'] ?? null]);

                return ['success' => false, 'message' => 'Teacher not found'];
            }

            // Validate room exists
            $room = Room::find($item['room_id'] ?? null);
            if (! $room) {
                Log::error('Room not found:', ['room_id' => $item['room_id'] ?? null]);

                return ['success' => false, 'message' => 'Room not found'];
            }

            // Find timeslot
            $timeslot = Timeslot::find($item['timeslot_id'] ?? null);
            if (! $timeslot) {
                Log::error('Timeslot not found:', ['timeslot_id' => $item['timeslot_id'] ?? null]);

                return ['success' => false, 'message' => 'Timeslot not found'];
            }

            // Validate semester consistency - commented out for manual scheduling flexibility
            // if ($courseOffering->semester_id !== $semester->id) {
            //     Log::error('Semester mismatch:', [
            //         'course_offering_semester_id' => $courseOffering->semester_id,
            //         'selected_semester_id' => $semester->id
            //     ]);
            //     return ['success' => false, 'message' => 'Selected course offering does not belong to selected semester'];
            // }

            // Find existing section
            $section = Section::find($item['section_id'] ?? null);
            if (! $section) {
                Log::error('Section not found:', ['section_id' => $item['section_id'] ?? null]);

                return ['success' => false, 'message' => 'Section not found'];
            }

            // Validate that section belongs to the course offering
            if ($section->course_offering_id !== $courseOffering->id) {
                Log::error('Section does not belong to course offering:', [
                    'section_course_offering_id' => $section->course_offering_id,
                    'course_offering_id' => $courseOffering->id,
                ]);

                return ['success' => false, 'message' => 'Selected section does not belong to the course offering'];
            }

            // Attach teacher to section if not already assigned
            if (! $section->teachers->contains($teacher)) {
                $section->teachers()->attach($teacher->id);
            }

            // Prevent teacher conflict at the selected timeslot
            if ($this->hasTeacherConflict($teacher, $timeslot, $section->id)) {
                return ['success' => false, 'message' => 'The selected teacher has another class at this timeslot'];
            }

            // Prevent scheduling conflicts for room at this timeslot
            if (Schedule::where('room_id', $room->id)->where('timeslot_id', $timeslot->id)->exists()) {
                return ['success' => false, 'message' => 'Room is already booked for this timeslot'];
            }

            // Enforce student conflict prevention
            $studentConflict = $this->hasStudentConflict($section, $timeslot);
            if ($studentConflict) {
                return ['success' => false, 'message' => 'One or more students are already enrolled in another section at this timeslot'];
            }

            // Enforce room capacity constraint
            if ($room->capacity < $section->capacity) {
                return ['success' => false, 'message' => "Room capacity ({$room->capacity}) is insufficient for section size ({$section->capacity})"];
            }

            // Enforce room type constraint
            $course = $section->courseOffering->course;
            if (! $this->isRoomSuitableForCourse($room, $course)) {
                return ['success' => false, 'message' => 'Room type is not suitable for this course'];
            }

            // Create or update schedule entry
            Log::info('Creating schedule entry:', [
                'section_id' => $section->id,
                'timeslot_id' => $timeslot->id,
                'room_id' => $room->id,
            ]);

            $schedule = Schedule::updateOrCreate(
                ['section_id' => $section->id, 'timeslot_id' => $timeslot->id],
                ['room_id' => $room->id]
            );

            Log::info('Schedule created successfully:', ['schedule_id' => $schedule->id]);

            return ['success' => true, 'message' => 'Schedule created successfully'];

        } catch (\Exception $e) {
            Log::error('Error creating schedule item:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => 'Error creating schedule: '.$e->getMessage()];
        }
    }

    /**
     * Find existing timeslot or create new one
     */
    private function findOrCreateTimeslot(string $day, string $startTime, string $endTime): Timeslot
    {
        $timeslot = Timeslot::where('day_of_week', $day)
            ->where('start_time', $startTime)
            ->where('end_time', $endTime)
            ->first();

        if (! $timeslot) {
            $timeslot = Timeslot::create([
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'slot_code' => $this->generateSlotCode($day, $startTime, $endTime),
            ]);
        }

        return $timeslot;
    }

    /**
     * Generate a unique slot code
     */
    private function generateSlotCode(string $day, string $startTime, string $endTime): string
    {
        $dayCode = strtoupper(substr($day, 0, 3));
        $startCode = str_replace(':', '', $startTime);
        $endCode = str_replace(':', '', $endTime);

        return "{$dayCode}_{$startCode}_{$endCode}";
    }

    /**
     * Validate time format (HH:MM)
     */
    private function isValidTime(string $time): bool
    {
        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }

    public function generateAuto(Request $request)
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return back()->with('error', 'Please select a semester for automatic generation.');
        }

        $result = $this->autoSchedulerService->generateSchedule($semesterId);

        if ($result['success']) {
            return redirect()->route('schedules.generate.show')
                ->with('success', $result['message'] ?? 'Schedule generated successfully');
        }

        return back()->with('error', $result['message'] ?? 'Schedule generation failed');
    }

    public function show(Schedule $schedule)
    {
        $this->authorize('view', $schedule);

        $schedule->load([
            'section.courseOffering.course',
            'section.courseOffering.semester',
            'section.teachers.user',
            'room',
            'timeslot',
        ]);

        return Inertia::render('Schedules/Show', [
            'schedule' => $schedule,
        ]);
    }

    public function edit(Schedule $schedule)
    {
        try {
            Log::info('Edit method called for schedule ID: '.$schedule->id);
            $this->authorize('update', $schedule);

            $schedule->load([
                'section.courseOffering.course',
                'section.courseOffering.semester',
                'section.teachers.user',
                'room',
                'timeslot',
            ]);

            $sections = Section::with(['courseOffering.course', 'courseOffering.semester', 'teachers.user'])->get();
            $teachers = Teacher::with('user')->get();

            $rooms = Room::all();
            $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();
            $otherSchedules = Schedule::with(['section.teachers.user', 'timeslot'])
                ->where('id', '!=', $schedule->id)
                ->get();

            Log::info('Data loaded successfully', [
                'schedule_count' => 1,
                'sections_count' => $sections->count(),
                'rooms_count' => $rooms->count(),
                'timeslots_count' => $timeslots->count(),
                'other_schedules_count' => $otherSchedules->count(),
                'sample_section' => $sections->first(),
                'all_sections' => $sections->take(3)->toArray(),
            ]);

            return Inertia::render('Schedules/Edit', [
                'schedule' => $schedule,
                'sections' => $sections,
                'teachers' => $teachers,
                'rooms' => $rooms,
                'timeslots' => $timeslots,
                'otherSchedules' => $otherSchedules,
            ]);
        } catch (\Exception $e) {
            Log::error('Edit method error: '.$e->getMessage(), [
                'schedule_id' => $schedule->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to load edit form: '.$e->getMessage());
        }
    }

    public function update(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'section_id' => 'required|exists:sections,id',
            'room_id' => 'required|exists:rooms,id',
            'timeslot_id' => 'required|exists:timeslots,id',
            'teacher_id' => 'nullable|exists:teachers,id',
        ]);

        if (! empty($validated['teacher_id'])) {
            $section = Section::find($validated['section_id']);
            if ($section && ! $section->teachers->contains($validated['teacher_id'])) {
                $section->teachers()->attach($validated['teacher_id']);
            }
        }

        $schedule->update([
            'section_id' => $validated['section_id'],
            'room_id' => $validated['room_id'],
            'timeslot_id' => $validated['timeslot_id'],
        ]);

        return redirect()->route('schedules.show', $schedule->id)
            ->with('success', 'Schedule updated successfully!');
    }

    public function assignTeacher(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $result = $this->schedulingService->assignTeacher($schedule->id, $request->teacher_id);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function assignRoom(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $request->validate([
            'room_id' => 'required|exists:rooms,id',
        ]);

        $result = $this->schedulingService->assignRoom($schedule->id, $request->room_id);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function assignTimeslot(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $request->validate([
            'timeslot_id' => 'required|exists:timeslots,id',
        ]);

        $result = $this->schedulingService->assignTimeslot($schedule->id, $request->timeslot_id);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroy(Schedule $schedule)
    {
        $this->authorize('delete', $schedule);

        $this->repository->delete($schedule->id);

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule deleted successfully.');
    }

    /**
     * Check if any students in the section have conflicts at the given timeslot
     */
    private function hasStudentConflict($section, $timeslot)
    {
        foreach ($section->enrollments as $enrollment) {
            $conflict = Schedule::whereHas('section.enrollments', function ($q) use ($enrollment) {
                $q->where('student_id', $enrollment->student_id);
            })->where('timeslot_id', $timeslot->id)
                ->where('section_id', '!=', $section->id)
                ->exists();

            if ($conflict) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the selected teacher has another schedule at the same timeslot
     */
    private function hasTeacherConflict($teacher, $timeslot, $excludeSectionId = null)
    {
        $query = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
            $q->where('teachers.id', $teacher->id);
        })->where('timeslot_id', $timeslot->id);

        if ($excludeSectionId) {
            $query->where('section_id', '!=', $excludeSectionId);
        }

        return $query->exists();
    }

    /**
     * Check if teacher has available hours for this assignment
     */
    private function checkTeacherWorkload($teacher, $timeslot)
    {
        $currentHours = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
            $q->where('teachers.id', $teacher->id);
        })->count();

        $maxHours = $teacher->max_hours_per_week ?? 20;

        if ($currentHours >= $maxHours) {
            return [
                'valid' => false,
                'message' => "Teacher has reached maximum weekly hours ({$currentHours}/{$maxHours})",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if room is suitable for course based on type and requirements
     */
    private function isRoomSuitableForCourse($room, $course)
    {
        $roomType = strtolower(trim((string) ($room->type ?? 'lecture')));
        $requiredRoomType = strtolower(trim((string) ($course->required_room_type ?? '')));
        $courseLevel = strtolower(trim((string) ($course->level ?? 'undergraduate')));
        $courseName = strtolower(trim((string) ($course->course_name ?? '')));

        if ($requiredRoomType === '' && str_contains($courseName, 'lab')) {
            $requiredRoomType = 'lab';
        }

        if (in_array($requiredRoomType, ['', 'any', 'lecture'], true)) {
            if ($courseLevel === 'graduate') {
                return in_array($roomType, ['seminar', 'conference', 'lecture'], true);
            }

            return in_array($roomType, ['lecture', 'classroom', 'seminar', 'conference'], true);
        }

        if ($requiredRoomType === 'lab') {
            return in_array($roomType, ['lab', 'laboratory', 'computer-lab', 'computer_lab'], true);
        }

        if ($requiredRoomType === 'seminar') {
            return in_array($roomType, ['seminar', 'conference', 'lecture'], true);
        }

        return $roomType === $requiredRoomType;
    }
}

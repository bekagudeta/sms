<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Room;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Timeslot;
use App\Services\SchedulingService;
use App\Services\AutoSchedulerService;
use App\Repositories\ScheduleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    protected $schedulingService;
    protected $repository;
    protected $autoSchedulerService;

    public function __construct(SchedulingService $schedulingService, ScheduleRepository $repository, AutoSchedulerService $autoSchedulerService)
    {
        $this->schedulingService = $schedulingService;
        $this->repository = $repository;
        $this->autoSchedulerService = $autoSchedulerService;
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
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, $request->input('page', 1));
        }
        // Students should only see schedules for their enrolled sections.
        elseif ($role === 'student') {
            $student = $user->student;
            $schedules = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, $request->input('page', 1));

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
            'currentSemester' => $request->input('semester_id')
        ]);
    }

    public function showGenerateForm()
    {
        $courseOfferings = \App\Models\CourseOffering::with(['course', 'semester', 'sections.teachers'])->get();
        $teachers = Teacher::with('department')->get();
        $rooms = Room::all();
        $semesters = Semester::all();
        $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();
        $sections = \App\Models\Section::with(['courseOffering.course', 'courseOffering.semester'])->get();
        
        // Debug: Check first section data
        $firstSection = $sections->first();
        Log::info('First section data:', [
            'section_id' => $firstSection?->id,
            'section_name' => $firstSection?->section_name,
            'course_offering_id' => $firstSection?->course_offering_id,
            'course_offering' => $firstSection?->courseOffering?->toArray(),
            'course' => $firstSection?->courseOffering?->course?->toArray()
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

    public function generate(Request $request)
    {
        if (!$request->isMethod('post')) {
            return back()->with('error', 'Invalid request method. Please use the form to submit schedules.');
        }

        try {
            // Extract and validate request data
            $requestData = $request->input('data', $request->all());
            $semesterId = $requestData['semester_id'] ?? null;
            $semesterName = $requestData['semester'] ?? null;
            $scheduleItems = $requestData['schedule'] ?? [];

            // Validate input
            $validationResult = $this->validateScheduleData($semesterId ?: $semesterName, $scheduleItems);
            if (!$validationResult['valid']) {
                return back()->with('error', $validationResult['message']);
            }

            // Determine semester
            $semesterModel = null;
            if ($semesterId) {
                $semesterModel = Semester::find($semesterId);
            }
            if (!$semesterModel && $semesterName) {
                $semesterModel = $this->findSemesterByName($semesterName);
            }

            if (!$semesterModel) {
                return back()->with('error', 'Invalid semester selected.');
            }

            // Process schedules in a transaction for data integrity
            DB::beginTransaction();
            
            try {
                // Clear existing schedules for this semester
                Schedule::where('semester_id', $semesterModel->id)->delete();
                
                $createdCount = 0;
                $errors = [];
                
                foreach ($scheduleItems as $index => $item) {
                    $result = $this->createScheduleItem($item, $semesterModel);
                    
                    if ($result['success']) {
                        $createdCount++;
                    } else {
                        $errors[] = "Item " . ($index + 1) . ": " . $result['message'];
                    }
                }
                
                DB::commit();
                
                // Prepare response message
                if ($createdCount > 0) {
                    $message = "Successfully generated {$createdCount} schedule(s) for {$semesterModel->name}.";
                    if (!empty($errors)) {
                        $message .= " Some items had errors: " . implode('; ', $errors);
                    }
                    return redirect()->route('schedules.index')
                        ->with('success', $message);
                } else {
                    DB::rollBack();
                    return back()->with('error', 'No schedules were created. ' . implode('; ', $errors));
                }
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Schedule generation failed: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'An error occurred while generating schedules: ' . $e->getMessage());
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
        
        if (empty($scheduleItems) || !is_array($scheduleItems)) {
            return ['valid' => false, 'message' => 'Please add at least one schedule item.'];
        }
        
        // Validate each schedule item has required fields (manual section-based scheduling)
        foreach ($scheduleItems as $index => $item) {
            $requiredFields = ['course_offering_id', 'section_name', 'teacher_id', 'room_id', 'timeslot_id'];
            foreach ($requiredFields as $field) {
                if (empty($item[$field])) {
                    return ['valid' => false, 'message' => "Schedule item " . ($index + 1) . " is missing required field: $field"];
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
            $semesterModel = Semester::where('name', $semester . $suffix . ' Semester')->first();
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
            // Find course offering
            $courseOffering = CourseOffering::find($item['course_offering_id'] ?? null);
            if (!$courseOffering) {
                return ['success' => false, 'message' => 'Course offering not found'];
            }

            // Validate teacher exists
            $teacher = Teacher::find($item['teacher_id'] ?? null);
            if (!$teacher) {
                return ['success' => false, 'message' => 'Teacher not found'];
            }

            // Validate room exists
            $room = Room::find($item['room_id'] ?? null);
            if (!$room) {
                return ['success' => false, 'message' => 'Room not found'];
            }

            // Find timeslot
            $timeslot = Timeslot::find($item['timeslot_id'] ?? null);
            if (!$timeslot) {
                return ['success' => false, 'message' => 'Timeslot not found'];
            }

            // Validate semester consistency
            if ($courseOffering->semester_id !== $semester->id) {
                return ['success' => false, 'message' => 'Selected course offering does not belong to selected semester'];
            }

            // Ensure section exists for this offering
            $section = Section::firstOrCreate(
                [
                    'course_offering_id' => $courseOffering->id,
                    'section_name' => $item['section_name'] ?? 'A'
                ],
                [
                    'capacity' => $item['capacity'] ?? $courseOffering->expected_students ?? 30
                ]
            );

            // Attach teacher if not assigned for section yet
            if (!$section->teachers()->where('teacher_id', $teacher->id)->exists()) {
                $section->teachers()->attach($teacher->id);
            }

            // Prevent scheduling conflicts for room and teacher at this timeslot
            if (Schedule::where('room_id', $room->id)->where('timeslot_id', $timeslot->id)->exists()) {
                return ['success' => false, 'message' => 'Room is already booked for this timeslot'];
            }

            $teacherConflict = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })->where('timeslot_id', $timeslot->id)->exists();

            if ($teacherConflict) {
                return ['success' => false, 'message' => 'Teacher is already assigned for this timeslot'];
            }

            // Enforce student conflict prevention
            $studentConflict = $this->hasStudentConflict($section, $timeslot);
            if ($studentConflict) {
                return ['success' => false, 'message' => 'One or more students are already enrolled in another section at this timeslot'];
            }

            // Enforce teacher workload constraints
            $teacherHoursCheck = $this->checkTeacherWorkload($teacher, $timeslot);
            if (!$teacherHoursCheck['valid']) {
                return ['success' => false, 'message' => $teacherHoursCheck['message']];
            }

            // Enforce room capacity constraint
            if ($room->capacity < $section->capacity) {
                return ['success' => false, 'message' => "Room capacity ({$room->capacity}) is insufficient for section size ({$section->capacity})"];
            }

            // Enforce room type constraint
            $course = $section->courseOffering->course;
            if (!$this->isRoomSuitableForCourse($room, $course)) {
                return ['success' => false, 'message' => 'Room type is not suitable for this course'];
            }

            // Create or update schedule entry
            Schedule::updateOrCreate(
                ['section_id' => $section->id, 'timeslot_id' => $timeslot->id],
                ['room_id' => $room->id]
            );

            return ['success' => true, 'message' => 'Schedule created successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error creating schedule: ' . $e->getMessage()];
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
        
        if (!$timeslot) {
            $timeslot = Timeslot::create([
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'slot_code' => $this->generateSlotCode($day, $startTime, $endTime)
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
        // Allow this potentially long-running generation to complete without hitting PHP's default time limit.
        // If this still takes too long, consider moving the work to a queued job and returning immediately.
        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $semesterId = $request->input('semester_id');
        
        if (!$semesterId) {
            return back()->with('error', 'Please select a semester for automatic generation.');
        }

        $result = $this->autoSchedulerService->generateSchedule($semesterId);

        if ($result['success']) {
            $message = "Automatic schedule generation completed! 
                       {$result['scheduled']} courses scheduled, 
                       {$result['conflicts']} courses had conflicts.";
            
            if ($result['conflicts'] > 0) {
                $message .= " Check conflicts below for details.";
            }
            
            return redirect()->route('schedules.index')
                ->with('success', $message)
                ->with('conflicts', $this->autoSchedulerService->getConflicts());
        }

        return back()->with('error', $result['message']);
    }

    public function show(Schedule $schedule)
    {
        $this->authorize('view', $schedule);

        $schedule->load([ 
            'section.courseOffering.course',
            'section.courseOffering.semester',
            'section.teachers.user',
            'room',
            'timeslot'
        ]);

        return Inertia::render('Schedules/Show', [
            'schedule' => $schedule
        ]);
    }

    public function edit(Schedule $schedule)
    {
        try {
            Log::info('Edit method called for schedule ID: ' . $schedule->id);
            $this->authorize('update', $schedule);

            $schedule->load([
                'section.courseOffering.course',
                'section.courseOffering.semester',
                'section.teachers.user',
                'room',
                'timeslot'
            ]);

            $sections = Section::all();

            $rooms = Room::all();
            $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();

            Log::info('Data loaded successfully', [
                'schedule_count' => 1,
                'sections_count' => $sections->count(),
                'rooms_count' => $rooms->count(),
                'timeslots_count' => $timeslots->count(),
                'sample_section' => $sections->first(),
                'all_sections' => $sections->take(3)->toArray()
            ]);

            return Inertia::render('Schedules/Edit', [
                'schedule' => $schedule,
                'sections' => $sections,
                'rooms' => $rooms,
                'timeslots' => $timeslots
            ]);
        } catch (\Exception $e) {
            Log::error('Edit method error: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->with('error', 'Failed to load edit form: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'section_id' => 'required|exists:sections,id',
            'room_id' => 'required|exists:rooms,id',
            'timeslot_id' => 'required|exists:timeslots,id',
        ]);

        $schedule->update($validated);

        return redirect()->route('schedules.show', $schedule->id)
            ->with('success', 'Schedule updated successfully!');
    }

    public function assignTeacher(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id'
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
            'room_id' => 'required|exists:rooms,id'
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
            'timeslot_id' => 'required|exists:timeslots,id'
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
     * Check if teacher has available hours for this assignment
     */
    private function checkTeacherWorkload($teacher, $timeslot)
    {
        $currentHours = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->count();
        
        $maxHours = $teacher->max_hours_per_week ?? 20;
        
        if ($currentHours >= $maxHours) {
            return [
                'valid' => false,
                'message' => "Teacher has reached maximum weekly hours ({$currentHours}/{$maxHours})"
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Check if room is suitable for course based on type and requirements
     */
    private function isRoomSuitableForCourse($room, $course)
    {
        $roomType = $room->type ?? 'lecture';
        $courseLevel = $course->level ?? 'undergraduate';
        
        // Labs require lab rooms
        if (str_contains(strtolower($course->course_name ?? ''), 'lab') && $roomType !== 'lab') {
            return false;
        }
        
        // Seminars can use seminar or lecture rooms
        if ($courseLevel === 'graduate' && !in_array($roomType, ['seminar', 'lecture', 'conference'])) {
            return false;
        }
        
        return true;
    }
}
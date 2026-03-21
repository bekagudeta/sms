<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Room;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\Timeslot;
use App\Services\SchedulingService;
use App\Services\AutoSchedulerService;
use App\Repositories\ScheduleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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
            $teacher = Teacher::where('email', $user->email)->first();
            $schedules = $teacher
                ? $this->repository->paginateByTeacher($teacher->id, 10)
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, $request->input('page', 1));
        }
        // Students should only see schedules for their semester.
        elseif ($role === 'student') {
            $student = \App\Models\Student::where('email', $user->email)->first();
            $schedules = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, $request->input('page', 1));

            if ($student) {
                $semesterNumber = $student->semester;
                $semesterModel = Semester::where('name', $semesterNumber . ' Semester')->first();
                if ($semesterModel) {
                    $schedules = $this->repository->paginateBySemester($semesterModel->id, 10);
                }
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
        $courses = Course::with(['teacher', 'department', 'semester'])->get();
        $teachers = Teacher::with('department')->get();
        $rooms = Room::all();
        $semesters = Semester::all();
        
        return Inertia::render('Schedules/Generate', [
            'courses' => $courses,
            'teachers' => $teachers,
            'rooms' => $rooms,
            'semesters' => $semesters
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
        
        // Validate each schedule item has required fields
        foreach ($scheduleItems as $index => $item) {
            $requiredFields = ['course_id', 'teacher_id', 'room', 'day', 'start_time', 'end_time'];
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
            // Validate course exists
            $course = Course::find($item['course_id']);
            if (!$course) {
                return ['success' => false, 'message' => 'Course not found'];
            }
            
            // Validate teacher exists
            $teacher = Teacher::find($item['teacher_id']);
            if (!$teacher) {
                return ['success' => false, 'message' => 'Teacher not found'];
            }
            
            // Validate room exists
            $room = Room::where('room_code', $item['room'])->first();
            if (!$room) {
                return ['success' => false, 'message' => 'Room not found'];
            }
            
            // Validate time format
            if (!$this->isValidTime($item['start_time']) || !$this->isValidTime($item['end_time'])) {
                return ['success' => false, 'message' => 'Invalid time format'];
            }
            
            // Validate day
            if (!in_array($item['day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])) {
                return ['success' => false, 'message' => 'Invalid day'];
            }
            
            // Find or create timeslot
            $timeslot = $this->findOrCreateTimeslot($item['day'], $item['start_time'], $item['end_time']);

            // Prevent scheduling conflicts for room and teacher
            if (Schedule::where('semester_id', $semester->id)
                        ->where('timeslot_id', $timeslot->id)
                        ->where('room_id', $room->id)
                        ->exists()) {
                return ['success' => false, 'message' => 'Room is already booked for this timeslot'];
            }

            if (Schedule::where('semester_id', $semester->id)
                        ->where('timeslot_id', $timeslot->id)
                        ->where('teacher_id', $teacher->id)
                        ->exists()) {
                return ['success' => false, 'message' => 'Teacher is already assigned for this timeslot'];
            }

            // Create schedule
            Schedule::create([
                'course_id' => $item['course_id'],
                'teacher_id' => $item['teacher_id'],
                'room_id' => $room->id,
                'timeslot_id' => $timeslot->id,
                'semester_id' => $semester->id,
                'section' => $item['section'] ?? 'A',
                'max_students' => $course->student_count ?? 30,
                'status' => 'scheduled',
                'day' => $item['day'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time']
            ]);
            
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
        
        $schedule->load(['course', 'teacher', 'room', 'timeslot', 'semester']);
        
        return Inertia::render('Schedules/Show', [
            'schedule' => $schedule
        ]);
    }

    public function edit(Schedule $schedule)
    {
        $this->authorize('update', $schedule);
        
        $schedule->load(['course', 'teacher', 'room', 'timeslot', 'semester']);
        
        $courses = Course::with(['department', 'semester'])->get();
        $teachers = Teacher::with('department')->get();
        $rooms = Room::all();
        $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();
        $semesters = Semester::all();
        
        return Inertia::render('Schedules/Edit', [
            'schedule' => $schedule,
            'courses' => $courses,
            'teachers' => $teachers,
            'rooms' => $rooms,
            'timeslots' => $timeslots,
            'semesters' => $semesters
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:teachers,id',
            'room_id' => 'required|exists:rooms,id',
            'timeslot_id' => 'required|exists:timeslots,id',
            'semester_id' => 'required|exists:semesters,id',
            'section' => 'required|string|max:10',
            'max_students' => 'required|integer|min:1|max:200',
            'status' => 'required|in:scheduled,cancelled,pending',
        ]);

        // Get the timeslot to update day, start_time, and end_time
        $timeslot = Timeslot::find($validated['timeslot_id']);
        $validated['day'] = $timeslot->day_of_week;
        $validated['start_time'] = $timeslot->start_time;
        $validated['end_time'] = $timeslot->end_time;

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
}
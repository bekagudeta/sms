<?php

namespace App\Http\Controllers;

use App\Exports\CredentialsExport;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ExcelImportService $importService)
    {
        $this->importService = $importService;
        // Temporarily removed permission middleware for testing
        // $this->middleware('permission:import data');
    }

    public function index()
    {
        return Inertia::render('Imports/ImportExcel', [
            'entityCounts' => [
                'departments' => \App\Models\Department::count(),
                'semesters' => \App\Models\Semester::count(),
                'courses' => \App\Models\Course::count(),
                'course-offerings' => \App\Models\CourseOffering::count(),
                'sections' => \App\Models\Section::count(),
                'teachers' => \App\Models\Teacher::count(),
                'section-teachers' => DB::table('section_teachers')->count(),
                'rooms' => \App\Models\Room::count(),
                'timeslots' => \App\Models\Timeslot::count(),
                'students' => \App\Models\Student::count(),
                'enrollments' => \App\Models\Enrollment::count(),
            ],
        ]);
    }

    public function downloadTemplate(string $type)
    {
        $templates = $this->getTemplateDefinitions();

        abort_unless(isset($templates[$type]), 404, 'Unknown import template type.');

        $template = $templates[$type];

        return response()->streamDownload(function () use ($template) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $template['headers']);

            foreach ($template['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $template['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function getTemplateDefinitions(): array
    {
        return [
            'departments' => [
                'filename' => 'departments_template.csv',
                'headers' => ['code', 'name', 'description'],
                'rows' => [
                    ['CS', 'Computer Science', 'Computing and software programs'],
                ],
            ],
            'semesters' => [
                'filename' => 'semesters_template.csv',
                'headers' => ['name', 'code', 'start_date', 'end_date', 'is_active'],
                'rows' => [
                    ['1st Semester AY 2026-2027', '1ST-AY26', '2026-06-01', '2026-10-01', '1'],
                ],
            ],
            'courses' => [
                'filename' => 'courses_template.csv',
                'headers' => ['course_code', 'course_name', 'description', 'credits', 'hours_per_week', 'department_id', 'level', 'required_room_type'],
                'rows' => [
                    ['CS101', 'Introduction to Programming', 'Programming fundamentals', '3', '3', 'CS', 'undergraduate', 'lab'],
                ],
            ],
            'course-offerings' => [
                'filename' => 'course_offerings_template.csv',
                'headers' => ['course_code', 'semester_code', 'expected_students'],
                'rows' => [
                    ['CS101', '1ST-AY26', '40'],
                ],
            ],
            'sections' => [
                'filename' => 'sections_template.csv',
                'headers' => ['course_code', 'semester_code', 'section_name', 'capacity'],
                'rows' => [
                    ['CS101', '1ST-AY26', 'BSCS-1A', '40'],
                ],
            ],
            'teachers' => [
                'filename' => 'teachers_template.csv',
                'headers' => ['teacher_id', 'first_name', 'last_name', 'email', 'department_id', 'department_name', 'qualification', 'max_hours_per_week', 'phone'],
                'rows' => [
                    ['T-001', 'Maria', 'Santos', 'maria.santos@example.com', 'CS', 'Computer Science', 'MSCS', '18', '09171234567'],
                ],
            ],
            'section-teachers' => [
                'filename' => 'section_teachers_template.csv',
                'headers' => ['section_id', 'teacher_ids', 'append'],
                'rows' => [
                    ['1', 'T-001', 'no'],
                ],
            ],
            'rooms' => [
                'filename' => 'rooms_template.csv',
                'headers' => ['room_code', 'building', 'floor', 'capacity', 'type', 'has_projector', 'has_computers', 'computer_count'],
                'rows' => [
                    ['LAB-201', 'Main Building', '2', '40', 'lab', 'yes', 'yes', '40'],
                ],
            ],
            'timeslots' => [
                'filename' => 'timeslots_template.csv',
                'headers' => ['day_of_week', 'start_time', 'end_time', 'slot_code'],
                'rows' => [
                    ['Monday', '08:00', '09:30', 'MON_0800_0930'],
                ],
            ],
            'students' => [
                'filename' => 'students_template.csv',
                'headers' => ['student_id', 'first_name', 'last_name', 'email', 'department_id', 'department_name', 'level', 'section', 'phone', 'enrollment_date'],
                'rows' => [
                    ['STU0001', 'Juan', 'Dela Cruz', 'juan.delacruz@example.com', 'CS', 'Computer Science', '1', 'BSCS-1A', '09170000001', '2026-06-15'],
                ],
            ],
            'enrollments' => [
                'filename' => 'enrollments_template.csv',
                'headers' => ['student_id', 'section_id'],
                'rows' => [
                    ['STU0001', 'BSCS-1A'],
                ],
            ],
        ];
    }

    public function importStudents(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importStudents($request->file('file'));

        if ($result['success']) {
            return Excel::download(
                new CredentialsExport($result['credentials'] ?? []),
                'student_credentials.xlsx'
            );
        }

        return response()->json(['message' => $result['message']], 500);
    }

    public function importTeachers(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importTeachers($request->file('file'));

        if ($result['success']) {
            return Excel::download(
                new CredentialsExport($result['credentials'] ?? []),
                'teacher_credentials.xlsx'
            );
        }

        return response()->json(['message' => $result['message']], 500);
    }

    public function importCourses(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importCourses($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importCourseOfferings(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importCourseOfferings($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSections(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importSections($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importDepartments(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importDepartments($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importTimeslots(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importTimeslots($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSemesters(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importSemesters($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importEnrollments(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importEnrollments($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSectionTeachers(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importSectionTeachers($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importRooms(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $result = $this->importService->importRooms($request->file('file'));

        return $this->respondImportResult($result);
    }

    private function respondImportResult(array $result)
    {
        if (request()->wantsJson() || request()->ajax()) {
            $response = [
                'success' => $result['success'],
                'message' => $result['message'],
                'count' => $result['count'] ?? 0,
            ];
            
            if (isset($result['errors']) && !empty($result['errors'])) {
                $response['errors'] = $result['errors'];
            }
            
            return response()->json($response, $result['success'] ? 200 : 500);
        }

        if ($result['success']) {
            $message = $result['message'] . ' (' . ($result['count'] ?? 0) . ' records)';
            
            if (isset($result['errors']) && !empty($result['errors'])) {
                $message .= ' with ' . count($result['errors']) . ' errors. Check logs for details.';
            }
            
            return back()->with('success', $message);
        }

        return back()->with('error', $result['message']);
    }
}
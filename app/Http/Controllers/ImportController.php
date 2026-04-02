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

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value), '_'));
    }

    private function prepareImportFile($file, array $mappings, bool $skipHeader = true)
    {
        $rows = Excel::toArray([], $file)[0] ?? [];

        if (empty($rows)) {
            throw new \Exception('Import file appears empty.');
        }

        if ($skipHeader) {
            $sourceHeaders = array_map('trim', (array)$rows[0]);
            $dataRows = array_slice($rows, 1);
        } else {
            $maxCols = max(array_map(fn($r) => count((array)$r), $rows));
            $sourceHeaders = array_map(fn($i) => "column_{$i}", range(0, $maxCols - 1));
            $dataRows = $rows;
        }

        $normalizedSourceHeaders = array_map([$this, 'normalizeHeader'], $sourceHeaders);

        $mappingsPrepared = [];

        foreach ($mappings as $targetField => $sourceValue) {
            $sourceValue = trim((string)$sourceValue);
            $targetField = trim($targetField);

            if ($sourceValue === '' || $targetField === '') {
                continue;
            }

            if (is_numeric($sourceValue)) {
                $index = (int)$sourceValue;
                if ($index >= 0 && $index < count($sourceHeaders)) {
                    $mappingsPrepared[$targetField] = $index;
                }
                continue;
            }

            $mappedIndex = array_search($this->normalizeHeader($sourceValue), $normalizedSourceHeaders, true);
            if ($mappedIndex !== false) {
                $mappingsPrepared[$targetField] = $mappedIndex;
            }
        }

        foreach (array_keys($mappings) as $targetField) {
            $targetField = trim($targetField);
            if ($targetField === '' || isset($mappingsPrepared[$targetField])) {
                continue;
            }

            $guessIndex = array_search($this->normalizeHeader($targetField), $normalizedSourceHeaders, true);
            if ($guessIndex !== false) {
                $mappingsPrepared[$targetField] = $guessIndex;
            }
        }

        $configuredFields = array_filter(array_map('trim', array_keys($mappings)));
        $missingRequired = [];

        foreach ($configuredFields as $field) {
            if (!isset($mappingsPrepared[$field])) {
                $missingRequired[] = $field;
            }
        }

        if (!empty($missingRequired)) {
            throw new \Exception('Unable to map required field(s): ' . implode(', ', array_unique($missingRequired)));
        }

        $outputHeaders = array_keys($mappingsPrepared);
        $usedSourceIndices = array_values($mappingsPrepared);

        foreach ($sourceHeaders as $index => $sourceHeader) {
            if (!in_array($index, $usedSourceIndices, true)) {
                $outputHeaders[] = $sourceHeader ?: "column_{$index}";
                $mappingsPrepared[$sourceHeader ?: "column_{$index}"] = $index;
            }
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'sms_import_');
        if ($tmpPath === false) {
            throw new \Exception('Unable to create temporary file for import processing.');
        }

        $tmpCsv = $tmpPath . '.csv';
        rename($tmpPath, $tmpCsv);

        $handle = fopen($tmpCsv, 'w');
        if (!$handle) {
            throw new \Exception('Unable to write temporary import file.');
        }

        fputcsv($handle, $outputHeaders);

        foreach ($dataRows as $row) {
            $row = (array) $row;
            $outputRow = [];

            foreach ($outputHeaders as $header) {
                $sourceIndex = $mappingsPrepared[$header] ?? null;
                $outputRow[] = ($sourceIndex !== null && array_key_exists($sourceIndex, $row)) ? $row[$sourceIndex] : null;
            }

            fputcsv($handle, $outputRow);
        }

        fclose($handle);

        return $tmpCsv;
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
                'headers' => ['section_id', 'teacher_ids', 'teacher_id', 'append'],
                'rows' => [
                    ['1', 'T-001', '', 'no'],
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

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'entity_type' => 'required|string',
            'required_columns' => 'nullable|array',
            'optional_columns' => 'nullable|array',
            'skip_header' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $requiredColumns = array_map('trim', (array)$request->input('required_columns', []));
        $optionalColumns = array_map('trim', (array)$request->input('optional_columns', []));
        $skipHeader = $request->boolean('skip_header', true);

        $raw = Excel::toArray([], $file)[0] ?? [];
        if (empty($raw)) {
            return response()->json([ 'headers' => [], 'rows' => [], 'mapping' => [] ]);
        }

        if ($skipHeader) {
            $sourceHeaders = array_map('trim', (array)$raw[0]);
            $dataRows = array_slice($raw, 1);
        } else {
            $columns = max(array_map(fn($r) => count((array)$r), $raw));
            $sourceHeaders = array_map(fn($i) => "column_{$i}", range(0, $columns - 1));
            $dataRows = $raw;
        }

        $normalizedSourceHeaders = array_map([$this, 'normalizeHeader'], $sourceHeaders);
        $mapping = [];

        foreach (array_unique(array_merge($requiredColumns, $optionalColumns)) as $field) {
            $normalizedField = $this->normalizeHeader($field);

            // exact match
            $match = array_search($normalizedField, $normalizedSourceHeaders, true);

            // fuzzy match
            if ($match === false) {
                foreach ($normalizedSourceHeaders as $index => $normalizedHeader) {
                    if (
                        str_contains($normalizedHeader, $normalizedField) ||
                        str_contains($normalizedField, $normalizedHeader) ||
                        ($normalizedField === 'department_id' && in_array($normalizedHeader, ['department_code', 'department_name'], true))
                    ) {
                        $match = $index;
                        break;
                    }
                }
            }

            // fallback id rules
            if ($match === false && str_ends_with($normalizedField, '_id')) {
                $base = substr($normalizedField, 0, -3);
                foreach ($normalizedSourceHeaders as $index => $normalizedHeader) {
                    if (in_array($normalizedHeader, ["{$base}_id", "{$base}_code", "{$base}_name", $base], true)) {
                        $match = $index;
                        break;
                    }
                }
            }

            if ($match !== false) {
                $mapping[$field] = (string)$match;
            }
        }

        return response()->json([
            'headers' => $sourceHeaders,
            'rows' => array_slice($dataRows, 0, 5),
            'mapping' => $mapping,
        ]);
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'entity_type' => 'required|string',
            'mappings' => 'nullable|array',
            'skip_header' => 'nullable|boolean',
            'validation_mode' => 'nullable|string'
        ]);

        $entityType = $request->input('entity_type');
        $file = $request->file('file');
        $mappings = $request->input('mappings', []);
        $skipHeader = $request->boolean('skip_header', true);

        // Map entity types to import methods
        $entityMethodMap = [
            'students' => 'importStudents',
            'teachers' => 'importTeachers',
            'courses' => 'importCourses',
            'departments' => 'importDepartments',
            'semesters' => 'importSemesters',
            'course-offerings' => 'importCourseOfferings',
            'sections' => 'importSections',
            'section-teachers' => 'importSectionTeachers',
            'timeslots' => 'importTimeslots',
            'rooms' => 'importRooms',
            'enrollments' => 'importEnrollments',
        ];

        if (!isset($entityMethodMap[$entityType])) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown entity type: ' . $entityType
            ], 400);
        }

        $processedFile = $file;

        if (!empty($mappings)) {
            try {
                $processedFile = $this->prepareImportFile($file, $mappings, $skipHeader);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: ' . $e->getMessage()
                ], 422);
            }
        }

        $method = $entityMethodMap[$entityType];

        try {
            $result = $this->$method($processedFile);
        } finally {
            if (is_string($processedFile) && file_exists($processedFile) && $processedFile !== $file->getRealPath()) {
                @unlink($processedFile);
            }
        }

        return $this->respondImportResult($result);
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
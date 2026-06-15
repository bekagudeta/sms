<?php

namespace App\Http\Controllers;

use App\Exports\CredentialsExport;
use App\Http\Requests\ImportFileRequest;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Room;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timeslot;
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
        $this->middleware('permission:import data');
    }

    public function index()
    {
        return Inertia::render('Imports/ImportExcel', [
            'entityCounts' => [
                'departments' => Department::count(),
                'semesters' => Semester::count(),
                'courses' => Course::count(),
                'course-offerings' => CourseOffering::count(),
                'sections' => Section::count(),
                'teachers' => Teacher::count(),
                'section-teachers' => DB::table('section_teachers')->count(),
                'rooms' => Room::count(),
                'timeslots' => Timeslot::count(),
                'students' => Student::count(),
                'enrollments' => Enrollment::count(),
            ],
            'importOptions' => $this->getFrontendImportOptions(),
        ]);
    }

    public function downloadTemplate(string $type)
    {
        $templates = $this->getTemplateDefinitions();

        abort_unless(isset($templates[$type]), 404, 'Unknown import template type.');

        $template = $templates[$type];

        return response()->streamDownload(function () use ($template) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $template['template_headers']);

            foreach ($template['template_rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $template['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function getImportDefinitions(): array
    {
        return config('imports.entities', []);
    }

    private function getTemplateDefinitions(): array
    {
        return $this->getImportDefinitions();
    }

    private function getFrontendImportOptions(): array
    {
        return collect($this->getImportDefinitions())
            ->map(function (array $definition, string $value) {
                return [
                    'value' => $value,
                    'label' => $definition['label'],
                    'category' => $definition['category'],
                    'description' => $definition['description'],
                    'dependencies' => $definition['dependencies'],
                    'requiredColumns' => implode(', ', $definition['required_columns']),
                    'optionalColumns' => implode(', ', $definition['optional_columns']),
                    'requiredColumnList' => $definition['required_columns'],
                    'optionalColumnList' => $definition['optional_columns'],
                    'templateHeaders' => $definition['template_headers'],
                    'note' => $definition['note'],
                ];
            })
            ->values()
            ->all();
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
            $sourceHeaders = array_map('trim', (array) $rows[0]);
            $dataRows = array_slice($rows, 1);
        } else {
            $maxCols = max(array_map(fn ($r) => count((array) $r), $rows));
            $sourceHeaders = array_map(fn ($i) => "column_{$i}", range(0, $maxCols - 1));
            $dataRows = $rows;
        }

        $normalizedSourceHeaders = array_map([$this, 'normalizeHeader'], $sourceHeaders);

        $mappingsPrepared = [];

        foreach ($mappings as $targetField => $sourceValue) {
            $sourceValue = trim((string) $sourceValue);
            $targetField = trim($targetField);

            if ($sourceValue === '' || $targetField === '') {
                continue;
            }

            if (is_numeric($sourceValue)) {
                $index = (int) $sourceValue;
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
            if (! isset($mappingsPrepared[$field])) {
                $missingRequired[] = $field;
            }
        }

        if (! empty($missingRequired)) {
            throw new \Exception('Unable to map required field(s): '.implode(', ', array_unique($missingRequired)));
        }

        $outputHeaders = array_keys($mappingsPrepared);
        $usedSourceIndices = array_values($mappingsPrepared);

        foreach ($sourceHeaders as $index => $sourceHeader) {
            if (! in_array($index, $usedSourceIndices, true)) {
                $outputHeaders[] = $sourceHeader ?: "column_{$index}";
                $mappingsPrepared[$sourceHeader ?: "column_{$index}"] = $index;
            }
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'sms_import_');
        if ($tmpPath === false) {
            throw new \Exception('Unable to create temporary file for import processing.');
        }

        $tmpCsv = $tmpPath.'.csv';
        rename($tmpPath, $tmpCsv);

        $handle = fopen($tmpCsv, 'w');
        if (! $handle) {
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

    public function previewImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
            'entity_type' => 'required|string|in:'.implode(',', array_keys($this->getImportDefinitions())),
            'required_columns' => 'nullable|array',
            'optional_columns' => 'nullable|array',
            'skip_header' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        
        // Validate file content for security threats
        try {
            $this->validateFileContent($file);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File validation failed: '.$e->getMessage(),
            ], 422);
        }

        $definition = $this->getImportDefinitions()[$request->input('entity_type')];
        $requiredColumns = $definition['required_columns'];
        $optionalColumns = $definition['optional_columns'];
        $skipHeader = $request->boolean('skip_header', true);

        $raw = Excel::toArray([], $file)[0] ?? [];
        if (empty($raw)) {
            return response()->json(['headers' => [], 'rows' => [], 'mapping' => []]);
        }

        if ($skipHeader) {
            $sourceHeaders = array_map('trim', (array) $raw[0]);
            $dataRows = array_slice($raw, 1);
        } else {
            $columns = max(array_map(fn ($r) => count((array) $r), $raw));
            $sourceHeaders = array_map(fn ($i) => "column_{$i}", range(0, $columns - 1));
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
                $mapping[$field] = (string) $match;
            }
        }

        return response()->json([
            'headers' => $sourceHeaders,
            'rows' => array_slice($dataRows, 0, 5),
            'mapping' => $mapping,
        ]);
    }

    public function bulkImport(ImportFileRequest $request)
    {
        $entityType = $request->input('entity_type');
        $file = $request->file('file');
        $mappings = $request->input('mappings', []);
        $skipHeader = $request->boolean('skip_header', true);
        
        // Validate file content for security threats
        try {
            $this->validateFileContent($file);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File validation failed: '.$e->getMessage(),
            ], 422);
        }
        
        $serviceMethod = $this->getImportDefinitions()[$entityType]['service_method'];

        $processedFile = $file;

        if (! empty($mappings)) {
            try {
                $processedFile = $this->prepareImportFile($file, $mappings, $skipHeader);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: '.$e->getMessage(),
                ], 422);
            }
        }

        try {
            $result = $this->importService->$serviceMethod($processedFile);
        } finally {
            if (is_string($processedFile) && file_exists($processedFile) && $processedFile !== $file->getRealPath()) {
                @unlink($processedFile);
            }
        }

        // Students/teachers imports generate login credentials. Stash the freshly
        // generated set in the session (plaintext only for this one-time download)
        // and signal the frontend to auto-download the credentials sheet.
        if (
            ($result['success'] ?? false)
            && in_array($entityType, ['students', 'teachers'], true)
            && ! empty($result['credentials'])
        ) {
            session([
                'import_credentials' => $result['credentials'],
                'import_credentials_type' => $entityType,
            ]);
            session()->flash('download_credentials', true);
        }

        return $this->respondImportResult($result);
    }

    /**
     * One-time download of credentials generated by the most recent
     * student/teacher import. Clears them from the session afterwards so the
     * plaintext is never retrievable twice.
     */
    public function downloadCredentials()
    {
        $credentials = session()->pull('import_credentials', []);
        $type = session()->pull('import_credentials_type', 'user');

        if (empty($credentials)) {
            return back()->with('error', 'No credentials are available to download. Please re-run the import.');
        }

        $filename = $type.'_credentials.xlsx';

        return Excel::download(new CredentialsExport($credentials), $filename);
    }

    public function importStudents(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importCourses($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importCourseOfferings(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importCourseOfferings($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSections(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importSections($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importDepartments(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importDepartments($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importTimeslots(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importTimeslots($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSemesters(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importSemesters($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importEnrollments(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importEnrollments($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importSectionTeachers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importSectionTeachers($request->file('file'));

        return $this->respondImportResult($result);
    }

    public function importRooms(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.config('imports.max_file_kb', 10240),
        ]);

        $result = $this->importService->importRooms($request->file('file'));

        return $this->respondImportResult($result);
    }

    /**
     * Validate file content for security threats
     * Prevents malicious Excel files with formulas or external data connections
     */
    private function validateFileContent($file): void
    {
        $filePath = $file->getRealPath();
        $fileContent = file_get_contents($filePath);

        if ($file->extension() === 'csv') {
            // For CSV, check for formula injection patterns
            $lines = explode("\n", $fileContent);
            foreach ($lines as $line) {
                // Check for formula injection indicators
                if (preg_match('/^[=@+\-]/', trim($line))) {
                    throw new \Exception('File contains suspicious formula patterns. CSV files must not start with =, @, +, or -.');
                }
            }
        } elseif (in_array($file->extension(), ['xlsx', 'xls'])) {
            // For Excel files, look for suspicious XML patterns in the ZIP structure
            if (function_exists('zip_open')) {
                $zip = zip_open($filePath);
                if (is_resource($zip)) {
                    while ($entry = zip_read($zip)) {
                        $name = zip_entry_name($entry);
                        // Check for VBA macros or external connections
                        if (preg_match('/\.(?:bin|xml)$/i', $name) && preg_match('/(VBA|externalConnections|oleObject)/i', $name)) {
                            zip_close($zip);
                            throw new \Exception('File contains VBA macros or external connections. Please use clean data files only.');
                        }
                    }
                    zip_close($zip);
                }
            }
        }
    }

    private function respondImportResult(array $result)
    {
        $isInertia = request()->header('X-Inertia') || request()->header('X-Inertia-Partial-Data');

        if (! $isInertia && (request()->wantsJson() || request()->ajax())) {
            $response = [
                'success' => $result['success'],
                'message' => $result['message'],
                'count' => $result['count'] ?? 0,
            ];

            if (isset($result['errors']) && ! empty($result['errors'])) {
                $response['errors'] = $result['errors'];
            }

            return response()->json($response, $result['success'] ? 200 : 500);
        }

        if ($result['success']) {
            $message = $result['message'].' ('.($result['count'] ?? 0).' records)';

            if (! empty($result['errors'])) {
                $message .= ' — '.$this->summarizeImportErrors($result['errors']);
            }

            return back()->with('success', $message);
        }

        $message = $result['message'];
        if (! empty($result['errors'])) {
            $message .= ' — '.$this->summarizeImportErrors($result['errors']);
        }

        return back()->with('error', $message);
    }

    /**
     * Build a readable, bounded summary of per-row import warnings so the user
     * can see WHY rows failed (e.g. "Semester not found") instead of just a count.
     */
    private function summarizeImportErrors(array $errors, int $limit = 5): string
    {
        $shown = array_slice(array_values($errors), 0, $limit);
        $summary = implode(' | ', $shown);

        $remaining = count($errors) - count($shown);
        if ($remaining > 0) {
            $summary .= " | …and {$remaining} more.";
        }

        return $summary;
    }
}

<?php

namespace App\Services;

use App\Imports\StudentsImport;
use App\Imports\TeachersImport;
use App\Imports\CoursesImport;
use App\Imports\DepartmentsImport;
use App\Imports\SemestersImport;
use App\Imports\CourseOfferingsImport;
use App\Imports\SectionsImport;
use App\Imports\SectionTeachersImport;
use App\Imports\EnrollmentsImport;
use App\Imports\TimeslotsImport;
use App\Imports\RoomsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ExcelImportService
{
    public function importStudents($file)
    {
        try {
            // Prevent timeouts for large imports (bcrypt hashing is CPU-intensive)
            ini_set('max_execution_time', 0);
            set_time_limit(0);

            $import = new StudentsImport();
            Excel::import($import, $file);
            
            return [
                'success' => true,
                'message' => 'Students imported successfully',
                'count' => $import->getRowCount(),
                'credentials' => $import->credentials,
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            Log::error('Student import validation failed: ' . $e->getMessage());
            $failures = $e->failures();
            $messages = array_map(function ($failure) {
                $row = $failure->row();
                $attribute = $failure->attribute();
                $errors = implode(' / ', $failure->errors());
                return "Row {$row} [{$attribute}]: {$errors}";
            }, $failures);

            return [
                'success' => false,
                'message' => 'Validation failed: ' . implode(' | ', $messages)
            ];
        } catch (\Exception $e) {
            Log::error('Student import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importTeachers($file)
    {
        try {
            // Prevent timeouts for large imports (bcrypt hashing is CPU-intensive)
            ini_set('max_execution_time', 0);
            set_time_limit(0);

            $import = new TeachersImport();
            Excel::import($import, $file);
            
            return [
                'success' => true,
                'message' => 'Teachers imported successfully',
                'count' => $import->getRowCount(),
                'credentials' => $import->credentials,
            ];
        } catch (\Exception $e) {
            Log::error('Teacher import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importCourses($file)
    {
        try {
            $import = new CoursesImport();
            Excel::import($import, $file);
            
            return [
                'success' => true,
                'message' => 'Courses imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Course import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importDepartments($file)
    {
        try {
            $import = new DepartmentsImport();
            Excel::import($import, $file);

            return [
                'success' => true,
                'message' => 'Departments imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Department import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importSemesters($file)
    {
        try {
            $import = new SemestersImport();
            Excel::import($import, $file);

            return [
                'success' => true,
                'message' => 'Semesters imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Semester import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importTimeslots($file)
    {
        try {
            $import = new TimeslotsImport();
            Excel::import($import, $file);
            
            return [
                'success' => true,
                'message' => 'Timeslots imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Timeslot import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importCourseOfferings($file)
    {
        try {
            $import = new CourseOfferingsImport();
            Excel::import($import, $file);

            $errors = $import->getErrors();
            $count = $import->getRowCount();
            $success = $count > 0 || empty($errors);
            $message = $success
                ? 'Course offerings imported successfully'
                : 'No course offerings were imported';
            
            if (!empty($errors)) {
                $message .= ' (with ' . count($errors) . ' warnings)';
            }

            return [
                'success' => $success,
                'message' => $message,
                'count' => $count,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('Course offerings import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importSections($file)
    {
        try {
            $import = new SectionsImport();
            Excel::import($import, $file);

            return [
                'success' => true,
                'message' => 'Sections imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Sections import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importEnrollments($file)
    {
        try {
            $import = new EnrollmentsImport();
            Excel::import($import, $file);

            $errors = $import->getErrors();

            $failures = $import->failures()->map(function ($failure) {
                return "Row {$failure->row()} [{$failure->attribute()}]: " . implode(' / ', $failure->errors());
            })->toArray();

            $errors = array_unique(array_merge($errors, $failures));

            $message = 'Enrollments imported successfully';
            if (!empty($errors)) {
                $message .= ' (with ' . count($errors) . ' warnings)';
            }

            $success = $import->getRowCount() > 0 || empty($errors);

            return [
                'success' => $success,
                'message' => $message,
                'count' => $import->getRowCount(),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('Enrollments import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importSectionTeachers($file)
    {
        try {
            $import = new SectionTeachersImport();
            Excel::import($import, $file);

            $errors = $import->getErrors();
            $count = $import->getRowCount();
            $success = $count > 0 || empty($errors);
            $message = $success
                ? 'Section teachers assigned successfully'
                : 'No section-teacher assignments were imported';
            
            if (!empty($errors)) {
                $message .= ' (with ' . count($errors) . ' warnings)';
            }

            return [
                'success' => $success,
                'message' => $message,
                'count' => $count,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('Section teachers import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }

    public function importRooms($file)
    {
        try {
            $import = new RoomsImport();
            Excel::import($import, $file);
            
            return [
                'success' => true,
                'message' => 'Rooms imported successfully',
                'count' => $import->getRowCount()
            ];
        } catch (\Exception $e) {
            Log::error('Room import failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
}
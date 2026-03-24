<?php

namespace App\Http\Controllers;

use App\Exports\CredentialsExport;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
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
        return Inertia::render('Imports/ImportExcel');
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
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'count' => $result['count'] ?? 0,
            ], $result['success'] ? 200 : 500);
        }

        if ($result['success']) {
            return back()->with('success', $result['message'] . ' (' . ($result['count'] ?? 0) . ' records)');
        }

        return back()->with('error', $result['message']);
    }
}
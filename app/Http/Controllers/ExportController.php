<?php

namespace App\Http\Controllers;

use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExportController extends Controller
{
    protected $exportService;

    public function __construct(ExcelExportService $exportService)
    {
        $this->exportService = $exportService;

        $this->middleware('permission:export schedule')->only([
            'exportSchedule',
            'exportStudents',
            'exportTeachers',
            'exportCredentials',
        ]);

        $this->middleware('role:teacher')->only([
            'exportTeacherStudents',
        ]);
    }

    public function exportSchedule(Request $request)
    {
        $semesterId = $request->input('semester_id');
        return $this->exportService->exportSchedule($semesterId);
    }

    public function exportStudents()
    {
        return $this->exportService->exportStudents();
    }

    public function exportTeachers()
    {
        return $this->exportService->exportTeachers();
    }

    public function exportCredentials()
    {
        return $this->exportService->exportCredentials();
    }

    public function exportTeacherStudents(Request $request)
    {
        $teacher = Auth::user()?->teacher;
        abort_unless($teacher, 403, 'Teacher profile missing.');

        return $this->exportService->exportTeacherStudents(
            $teacher->id,
            $request->integer('semester_id') ?: null
        );
    }
}
<?php

namespace App\Http\Controllers;

use App\Services\ExcelExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected $exportService;

    public function __construct(ExcelExportService $exportService)
    {
        $this->exportService = $exportService;
        $this->middleware('permission:export schedule');
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
}
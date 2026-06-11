<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentTypeAdminController extends Controller
{
    /**
     * Show the admin dashboard for managing student types
     */
    public function index()
    {
        // Get statistics
        $totalStudents = Student::count();
        $regularStudents = Student::where('student_type', 'regular')->count();
        $weekendStudents = Student::where('student_type', 'weekend')->count();
        
        // Get students for the table (with pagination)
        $students = Student::with(['user', 'department'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);
        
        // Get department statistics
        $departmentStats = Student::selectRaw('department_id, student_type, COUNT(*) as count')
            ->groupBy('department_id', 'student_type')
            ->with('department')
            ->get();

        return Inertia::render('Admin/StudentTypeAdminDashboard', [
            'stats' => [
                'total' => $totalStudents,
                'regular' => $regularStudents,
                'weekend' => $weekendStudents,
            ],
            'students' => $students,
            'departmentStats' => $departmentStats,
        ]);
    }
}

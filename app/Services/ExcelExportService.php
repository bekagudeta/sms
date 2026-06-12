<?php

namespace App\Services;

use App\Exports\ScheduleExport;
use App\Exports\StudentsExport;
use App\Exports\TeacherStudentsExport;
use App\Exports\TeachersExport;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    public function exportSchedule($semesterId = null): BinaryFileResponse
    {
        return Excel::download(new ScheduleExport($semesterId), 'schedule.xlsx');
    }

    public function exportStudents(): BinaryFileResponse
    {
        return Excel::download(new StudentsExport(), 'students.xlsx');
    }

    public function exportTeacherStudents(int $teacherId, ?int $semesterId = null): BinaryFileResponse
    {
        $suffix = $semesterId ? "_semester_{$semesterId}" : '';

        return Excel::download(
            new TeacherStudentsExport($teacherId, $semesterId),
            'my_students'.$suffix.'.xlsx'
        );
    }

    public function exportTeachers(): BinaryFileResponse
    {
        return Excel::download(new TeachersExport(), 'teachers.xlsx');
    }

    public function exportCredentials(): BinaryFileResponse
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'must_change_password')) {
            return Excel::download(new \App\Exports\CredentialsExport([]), 'credentials.xlsx');
        }


        // Get users that need credentials (must_change_password = true)
        $users = \App\Models\User::where('must_change_password', true)->get();

        $data = [];
        foreach ($users as $user) {
            // If user already has a plain password stored, use it
            if ($user->plain_password && $user->plain_password !== '') {
                $plainPassword = $user->plain_password;
            } else {
                // Generate a new temporary password if not already stored
                $plainPassword = \Illuminate\Support\Str::random(12);
                
                // Save the plain password to database
                $user->update(['plain_password' => $plainPassword]);
            }

            $data[] = [
                'name'     => $user->name,
                'email'    => $user->email,
                'password' => $plainPassword,
            ];
        }

        return Excel::download(new \App\Exports\CredentialsExport($data), 'credentials.xlsx');
    }
}
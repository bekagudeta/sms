<?php

namespace App\Services;

use App\Exports\ScheduleExport;
use App\Exports\StudentsExport;
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

    public function exportTeachers(): BinaryFileResponse
    {
        return Excel::download(new TeachersExport(), 'teachers.xlsx');
    }

    public function exportCredentials(): BinaryFileResponse
    {
        // Export credentials for users who are required to change password on first login
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'must_change_password') || ! Schema::hasColumn('users', 'plain_password')) {
            return Excel::download(new \App\Exports\CredentialsExport([]), 'credentials.xlsx');
        }

        $users = \App\Models\User::where('must_change_password', true)
            ->whereNotNull('plain_password')
            ->get(['name', 'email', 'plain_password']);

        $data = $users->map(function ($user) {
            return [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->plain_password,
            ];
        })->toArray();

        return Excel::download(new \App\Exports\CredentialsExport($data), 'credentials.xlsx');
    }
}
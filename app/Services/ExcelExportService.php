<?php

namespace App\Services;

use App\Exports\ScheduleExport;
use App\Exports\StudentsExport;
use App\Exports\TeacherStudentsExport;
use App\Exports\TeachersExport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    /**
     * Issue FRESH temporary passwords for every account that still hasn't
     * completed first login (must_change_password = true) and return them once
     * as a downloadable sheet.
     *
     * SECURITY: passwords are generated in-memory, only the hash is persisted,
     * and the plaintext exists solely for the lifetime of this response. No
     * password is ever stored in plaintext. Because this re-issues passwords,
     * any previously distributed sheet is invalidated on each export — that is
     * intentional ("you can only issue new credentials, never retrieve old ones").
     */
    public function exportCredentials(): BinaryFileResponse
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'must_change_password')) {
            return Excel::download(new \App\Exports\CredentialsExport([]), 'credentials.xlsx');
        }

        $data = [];

        \App\Models\User::where('must_change_password', true)
            ->chunkById(500, function ($users) use (&$data) {
                foreach ($users as $user) {
                    $plainPassword = Str::random(12);

                    $user->forceFill([
                        'password' => Hash::make($plainPassword),
                        'must_change_password' => true,
                    ])->save();

                    $data[] = [
                        'name'     => $user->name,
                        'email'    => $user->email,
                        'password' => $plainPassword,
                    ];
                }
            });

        return Excel::download(new \App\Exports\CredentialsExport($data), 'credentials.xlsx');
    }
}
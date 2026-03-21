<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TeachersImport;
use App\Exports\CredentialsExport;
use Maatwebsite\Excel\Facades\Excel;

class TeacherImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $import = new TeachersImport();
        Excel::import($import, $request->file('file'));

        // Export credentials immediately
        return Excel::download(
            new CredentialsExport($import->credentials),
            'teacher_credentials.xlsx'
        );
    }
}

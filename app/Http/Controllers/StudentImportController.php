<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\StudentsImport;
use App\Exports\CredentialsExport;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        $import = new StudentsImport();

        Excel::import($import, $request->file('file'));

        // 🔥 export credentials immediately
        return Excel::download(
            new CredentialsExport($import->credentials),
            'student_credentials.xlsx'
        );
    }
}

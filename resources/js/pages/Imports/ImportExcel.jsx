import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useForm } from '@inertiajs/react';

export default function ImportExcel() {
    const [importType, setImportType] = useState('');
    const [errorMessage, setErrorMessage] = useState(null);
    const [isDownloading, setIsDownloading] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const downloadFile = async (url, formData, defaultFilename) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
            credentials: 'same-origin',
        });

        if (!response.ok) {
            if (response.status === 419) {
                throw new Error('Session expired. Please reload the page and log in again.');
            }
            if (response.status === 401) {
                throw new Error('Unauthorized. Please log in again.');
            }

            const text = await response.text();

            // If JSON is returned, extract a message key and use that.
            let json;
            try {
                json = JSON.parse(text);
            } catch (parseErr) {
                json = null;
            }

            if (json?.message) {
                throw new Error(json.message);
            }
            if (json?.error) {
                throw new Error(json.error);
            }

            // Avoid rendering giant HTML error pages; try to extract a meaningful message.
            if (text.trim().startsWith('<')) {
                console.error('Import error response:', text);

                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');
                const heading = doc.querySelector('h1')?.textContent?.trim();
                const paragraph = doc.querySelector('p')?.textContent?.trim();
                const useful = heading || paragraph;

                throw new Error(useful || 'Import failed (check console for details)');
            }

            throw new Error(text || 'Upload failed');
        }

        const blob = await response.blob();
        const contentDisposition = response.headers.get('content-disposition');
        let filename = defaultFilename;
        if (contentDisposition) {
            const match = /filename="?([^";]+)"?/.exec(contentDisposition);
            if (match && match[1]) filename = match[1];
        }

        const urlObject = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = urlObject;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(urlObject);
    };

    const handleFileChange = (e) => {
        setData('file', e.target.files[0]);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        // For students and teachers, use the clean import routes so we can export credentials immediately
        const routeUrl = route(`import.${importType}`);
        const formData = new FormData();
        formData.append('file', data.file);

        if (importType === 'students' || importType === 'teachers') {
            try {
                setIsDownloading(true);
                await downloadFile(routeUrl, formData, `${importType}_credentials.xlsx`);

                // Reset the form after successful download
                reset();
                setImportType('');
                setErrorMessage(null);
            } catch (err) {
                console.error(err);
                let msg = err?.message || 'Import failed.';
                try {
                    const parsed = JSON.parse(msg);
                    msg = parsed?.message || parsed?.error || msg;
                } catch (parseErr) {
                    // if not JSON, keep original string
                }
                setErrorMessage(msg);
            } finally {
                setIsDownloading(false);
            }

            return;
        }

        try {
            setIsDownloading(true);
            const response = await fetch(routeUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const text = await response.text();
                let msg = 'Import failed.';
                try {
                    const json = JSON.parse(text);
                    msg = json?.message || json?.error || msg;
                } catch (e) {
                    if (text.trim().startsWith('<')) {
                        console.error('Import error response:', text);
                        msg = 'Import failed (check console for details)';
                    } else {
                        msg = text || msg;
                    }
                }

                throw new Error(msg);
            }

            // On success, refresh so flash message from backend is visible
            window.location.reload();
        } catch (err) {
            console.error(err);
            let msg = err?.message || 'Import failed.';
            try {
                const parsed = JSON.parse(msg);
                msg = parsed?.message || parsed?.error || msg;
            } catch (_parseErr) {
                // If error message is not JSON, keep it directly.
            }
            setErrorMessage(msg);
        } finally {
            setIsDownloading(false);
        }
    };

    const importOptions = [
        { value: 'students', label: 'Import Students' },
        { value: 'teachers', label: 'Import Teachers' },
        { value: 'courses', label: 'Import Courses' },
        { value: 'course-offerings', label: 'Import Course Offerings' },
        { value: 'sections', label: 'Import Sections' },
        { value: 'departments', label: 'Import Departments' },
        { value: 'timeslots', label: 'Import Timeslots' },
        { value: 'semesters', label: 'Import Semesters' },
        { value: 'rooms', label: 'Import Rooms' }
    ];

    return (
        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Import Data from Excel</h2>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Import Form */}
                        <div className="bg-gray-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold mb-4">Import File</h3>
                            
                            <select
                                className="w-full px-3 py-2 border rounded-md mb-4"
                                value={importType}
                                onChange={(e) => setImportType(e.target.value)}
                            >
                                <option value="">Select import type</option>
                                {importOptions.map(option => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>

                            {importType && (
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Choose Excel File
                                        </label>
                                        <input
                                            type="file"
                                            accept=".xlsx,.xls,.csv"
                                            onChange={handleFileChange}
                                            className="w-full"
                                            required
                                        />
                                        {errors.file && (
                                            <p className="text-red-500 text-sm mt-1">{errors.file}</p>
                                        )}
                                        {errorMessage && (
                                            <p className="text-red-500 text-sm mt-1">{errorMessage}</p>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={(processing || isDownloading) || !data.file}
                                        className="w-full bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 disabled:opacity-50"
                                    >
                                        {(processing || isDownloading) ? 'Importing...' : 'Import Data'}
                                    </button>
                                </form>
                            )}
                        </div>

                        {/* Instructions */}
                        <div className="bg-blue-50 p-6 rounded-lg">
                            <h3 className="text-lg font-semibold mb-4">Import Instructions</h3>
                            <ul className="space-y-2 text-sm text-gray-600">
                                <li>• File must be in Excel format (.xlsx, .xls) or CSV</li>
                                <li>• First row must contain column headers</li>
                                <li>• Required columns vary by import type</li>
                                <li>• Maximum file size: 10MB</li>
                                <li>• Data will be validated before import</li>
                                <li>• Duplicate records will be updated</li>
                            </ul>

                            <div className="mt-4">
                                <h4 className="font-semibold mb-2">Required Columns (per type):</h4>
                                {importType === 'students' && (
                                    <p className="text-sm">student_id, first_name, last_name, email, department_id OR (department_code, department_name), level, section, phone (optional), enrollment_date (optional)</p>
                                )}
                                {importType === 'teachers' && (
                                    <p className="text-sm">teacher_id, first_name, last_name, email, department_id, qualification, max_hours_per_week, phone (optional)</p>
                                )}
                                {importType === 'courses' && (
                                    <p className="text-sm">course_code, course_name, description (optional), credits, hours_per_week, department_id, level</p>
                                )}
                                {importType === 'course-offerings' && (
                                    <p className="text-sm">course_id, semester_id, expected_students</p>
                                )}
                                {importType === 'sections' && (
                                    <p className="text-sm">course_offering_id, section_name, capacity, teacher_ids (comma-separated, optional)</p>
                                )}
                                {importType === 'departments' && (
                                    <p className="text-sm">code, name, description (optional)</p>
                                )}
                                {importType === 'timeslots' && (
                                    <p className="text-sm">day_of_week, start_time, end_time, slot_code (optional)</p>
                                )}
                                {importType === 'rooms' && (
                                    <p className="text-sm">room_code, building, floor, capacity, type, has_projector (optional), has_computers (optional), computer_count (optional)</p>
                                )}
                                {importType === 'semesters' && (
                                    <p className="text-sm">name, code, start_date, end_date, is_active (optional, defaults to false)</p>
                                )}
                                {!importType && (
                                    <p className="text-sm text-gray-500">Select an import type to see required columns</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

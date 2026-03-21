import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Pagination from '@/Components/Pagination';
import { Link, router, usePage } from '@inertiajs/react';

export default function StudentsIndex({ students }) {
    const { props } = usePage();
    const filters = props.filters || {};
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');

    const studentsData = students?.data ?? students;

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/students', { search: searchTerm, page: 1 }, { preserveState: true, replace: true });
    };

    return (

        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="flex justify-between items-center mb-6">
                        <h2 className="text-2xl font-bold">Students Management</h2>
                        <Link
                            href="/students/create"
                            className="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600"
                        >
                            Add New Student
                        </Link>
                    </div>

                    {/* Search Bar */}
                    <form onSubmit={handleSearch} className="mb-4 flex flex-col sm:flex-row sm:items-center gap-2">
                        <input
                            type="text"
                            placeholder="Search students..."
                            className="w-full sm:flex-1 px-4 py-2 border rounded-md"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        <button
                            type="submit"
                            className="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition"
                        >
                            Search
                        </button>
                        {filters.search && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    router.get('/students', { search: '', page: 1 }, { preserveState: true, replace: true });
                                }}
                                className="w-full sm:w-auto bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition"
                            >
                                Clear
                            </button>
                        )}
                    </form>

                    {/* Students Table */}
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>

                            <tbody className="bg-white divide-y divide-gray-200">
                                {studentsData.map((student) => (
                                    <tr key={student.id}>
                                        <td className="px-6 py-4 whitespace-nowrap">{student.student_id}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {student.first_name} {student.last_name}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">{student.email}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">{student.department?.name}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">{student.semester}</td>
                                        <td className="px-6 py-4 whitespace-nowrap">

                                            <Link
                                                href={`/students/${student.id}/edit`}
                                                className="text-blue-600 hover:text-blue-900 mr-3"
                                            >
                                                Edit
                                            </Link>

                                            <Link
                                                href={`/students/${student.id}`}
                                                method="delete"
                                                as="button"
                                                className="text-red-600 hover:text-red-900"
                                                onClick={(e) => {
                                                    if (!confirm('Are you sure you want to delete this student?')) {
                                                        e.preventDefault();
                                                    }
                                                }}
                                            >
                                                Delete
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        {students.meta && (
                            <div className="text-sm text-gray-600">
                                Showing {students.meta.from} to {students.meta.to} of {students.meta.total} students
                            </div>
                        )}
                        <Pagination links={students.links} />
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
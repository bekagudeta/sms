import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * Professional Admin Dashboard for Student Type Management
 * 
 * Features:
 * - View and manage student types
 * - Real-time statistics
 * - Student listing with filters
 * - Bulk operations information
 */
export default function StudentTypeAdminDashboard({ stats, students, departmentStats }) {
    const [activeTab, setActiveTab] = useState('overview');

    return (
        <DashboardLayout>
            <Head title="Student Type Management" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Page Header */}
                    <div className="mb-6">
                        <h2 className="text-3xl font-bold text-gray-900">Student Type Management</h2>
                    </div>
                    {stats && (
                        <div className="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
                            <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                                <dt className="truncate text-sm font-medium text-gray-500">Total Students</dt>
                                <dd className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
                                    {stats.total || 0}
                                </dd>
                            </div>
                            <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                                <dt className="truncate text-sm font-medium text-gray-500">Regular Students</dt>
                                <dd className="mt-1 text-3xl font-semibold tracking-tight text-blue-600">
                                    {stats.regular || 0}
                                </dd>
                            </div>
                            <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
                                <dt className="truncate text-sm font-medium text-gray-500">Weekend Students</dt>
                                <dd className="mt-1 text-3xl font-semibold tracking-tight text-green-600">
                                    {stats.weekend || 0}
                                </dd>
                            </div>
                        </div>
                    )}

                    {/* Tab Navigation */}
                    <div className="mb-6 border-b border-gray-200">
                        <nav className="flex space-x-8" aria-label="Tabs">
                            <button
                                onClick={() => setActiveTab('overview')}
                                className={`border-b-2 px-1 py-4 text-sm font-medium ${
                                    activeTab === 'overview'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}
                            >
                                Overview
                            </button>
                            <button
                                onClick={() => setActiveTab('manage')}
                                className={`border-b-2 px-1 py-4 text-sm font-medium ${
                                    activeTab === 'manage'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}
                            >
                                Manage Students
                            </button>
                            <button
                                onClick={() => setActiveTab('bulk')}
                                className={`border-b-2 px-1 py-4 text-sm font-medium ${
                                    activeTab === 'bulk'
                                        ? 'border-indigo-500 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}
                            >
                                Bulk Operations
                            </button>
                        </nav>
                    </div>

                    {/* Content */}
                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        {activeTab === 'overview' && (
                            <div className="px-4 py-5 sm:p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Overview</h3>
                                <p className="text-gray-600">
                                    Student Type Management Dashboard - Manage regular and weekend student enrollments, track schedules, and perform bulk operations.
                                </p>
                            </div>
                        )}

                        {activeTab === 'manage' && (
                            <div className="px-4 py-5 sm:p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Manage Students</h3>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Student ID
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Name
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Department
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Type
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 bg-white">
                                            {students && students.data && students.data.length > 0 ? (
                                                students.data.map((student) => (
                                                    <tr key={student.id} className="hover:bg-gray-50">
                                                        <td className="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                                            {student.student_id}
                                                        </td>
                                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                            {student.first_name} {student.last_name}
                                                        </td>
                                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                            {student.department?.name || 'N/A'}
                                                        </td>
                                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold leading-5 ${
                                                                student.student_type === 'weekend'
                                                                    ? 'bg-green-100 text-green-800'
                                                                    : 'bg-blue-100 text-blue-800'
                                                            }`}>
                                                                {student.student_type}
                                                            </span>
                                                        </td>
                                                        <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                                            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold leading-5 ${
                                                                student.status === 'active'
                                                                    ? 'bg-green-100 text-green-800'
                                                                    : 'bg-red-100 text-red-800'
                                                            }`}>
                                                                {student.status}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan="5" className="px-6 py-4 text-center text-sm text-gray-500">
                                                        No students found
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {activeTab === 'bulk' && (
                            <div className="px-4 py-5 sm:p-6">
                                <h3 className="mb-4 text-lg font-medium text-gray-900">Bulk Operations</h3>
                                <div className="rounded-md bg-blue-50 p-4">
                                    <p className="text-sm text-blue-800">
                                        Use the API endpoint /api/students/bulk/import to perform bulk student type modifications.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

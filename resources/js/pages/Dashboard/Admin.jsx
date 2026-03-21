import React from 'react';
import { Link } from '@inertiajs/react';

export default function AdminDashboard({ stats, recentSchedules }) {
    return (
        <>
            <div className="py-12 bg-gray-50 min-h-screen" style={{ backgroundImage: 'url(https://source.unsplash.com/PG8NyM_Mcts)', backgroundSize: 'cover', backgroundPosition: 'center', backgroundRepeat: 'no-repeat' }}>
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
                        <div className="p-8 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                            <h2 className="text-3xl font-bold mb-2">Welcome to SMS Dashboard</h2>
                            <p className="text-blue-100">Manage students, teachers, courses, and schedules efficiently</p>
                        </div>
                        <div className="p-8">
                            {/* Stats Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                                <div className="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Students</h3>
                                            <p className="text-3xl font-bold">{stats.total_students}</p>
                                        </div>
                                        <div className="text-4xl opacity-80">👨‍🎓</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-green-500 to-green-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Teachers</h3>
                                            <p className="text-3xl font-bold">{stats.total_teachers}</p>
                                        </div>
                                        <div className="text-4xl opacity-80">👨‍🏫</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-yellow-500 to-orange-500 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Courses</h3>
                                            <p className="text-3xl font-bold">{stats.total_courses}</p>
                                        </div>
                                        <div className="text-4xl opacity-80">📚</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-purple-500 to-pink-500 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Schedules</h3>
                                            <p className="text-3xl font-bold">{stats.total_schedules}</p>
                                        </div>
                                        <div className="text-4xl opacity-80">📅</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Pending Credentials</h3>
                                            <p className="text-3xl font-bold">{stats.pending_credentials || 0}</p>
                                        </div>
                                        <div className="text-4xl opacity-80">🔑</div>
                                    </div>
                                </div>
                            </div>

                            {/* Recent Schedules */}
                            <div className="mb-10">
                                <h3 className="text-2xl font-bold mb-6 text-gray-800">Recent Schedules</h3>
                                <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-lg">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
                                                <tr>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Course</th>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Teacher</th>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Room</th>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Day</th>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                                                    <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {recentSchedules.length > 0 ? recentSchedules.map((schedule) => (
                                                    <tr key={schedule.id} className="hover:bg-gray-50 transition-colors duration-200">
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm font-medium text-gray-900">
                                                                {schedule.course.course_code}
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {schedule.course.course_name}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            {schedule.teacher?.first_name && schedule.teacher?.last_name 
                                                                ? `${schedule.teacher.first_name} ${schedule.teacher.last_name}` 
                                                                : 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            {schedule.room?.room_code || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            {schedule.timeslot?.day_of_week || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            {schedule.timeslot ? 
                                                                `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : 
                                                                'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <Link
                                                                href={`/schedules/${schedule.id}`}
                                                                className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors duration-200"
                                                            >
                                                                View Details
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                )) : (
                                                    <tr>
                                                        <td colSpan="6" className="px-6 py-8 text-center text-gray-500">
                                                            No schedules found. Generate a schedule to get started.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            {/* Quick Actions */}
                            <div>
                                <h3 className="text-2xl font-bold mb-6 text-gray-800">Quick Actions</h3>
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                                    <Link
                                        href="/admin/users/create"
                                        className="group bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">👤</div>
                                            <h4 className="text-lg font-semibold mb-2">Create User</h4>
                                            <p className="text-blue-100 text-sm">Add new students, teachers, or admin users</p>
                                        </div>
                                    </Link>
                                    <Link
                                        href="/schedules/generate"
                                        className="group bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">⚡</div>
                                            <h4 className="text-lg font-semibold mb-2">Generate Schedule</h4>
                                            <p className="text-indigo-100 text-sm">Automatically create schedules for courses</p>
                                        </div>
                                    </Link>
                                    <Link
                                        href="/import"
                                        className="group bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">📥</div>
                                            <h4 className="text-lg font-semibold mb-2">Import Data</h4>
                                            <p className="text-green-100 text-sm">Upload Excel files to import data</p>
                                        </div>
                                    </Link>
                                    <a
                                        href="/export/schedule"
                                        className="group bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 block"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">📤</div>
                                            <h4 className="text-lg font-semibold mb-2">Export Schedule</h4>
                                            <p className="text-purple-100 text-sm">Download schedules as Excel file</p>
                                        </div>
                                    </a>
                                    <a
                                        href="/export/credentials"
                                        className={`group bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-6 rounded-xl shadow-lg transition-all duration-300 hover:scale-105 block ${stats.pending_credentials <= 0 ? 'opacity-50 pointer-events-none' : 'hover:shadow-xl'}`}
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">🔑</div>
                                            <h4 className="text-lg font-semibold mb-2">Export Credentials</h4>
                                            <p className="text-indigo-100 text-sm">
                                                {stats.pending_credentials > 0 ?
                                                    'Download new login credentials' :
                                                    'No pending credentials to export'
                                                }
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
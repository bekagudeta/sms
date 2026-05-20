import React from 'react';
import { Link } from '@inertiajs/react';

export default function AdminDashboard({ stats, recentSchedules }) {
    return (
        <>
            <div className="py-12 bg-gray-50 min-h-screen" style={{ backgroundImage: 'linear-gradient(135deg, rgba(6,116,155,0.1) 0%, rgba(32,174,77,0.08) 50%, rgba(130,211,238,0.12) 100%)' }}>
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white/90 backdrop-blur-md overflow-hidden shadow-2xl sm:rounded-2xl border border-primary/20">
                        <div className="p-8 bg-gradient-to-r from-primary to-primary-dark text-white">
                            <h2 className="text-3xl font-extrabold mb-2">Welcome to SMS Dashboard</h2>
                            <p className="text-white/90">Manage students, teachers, courses, and schedules with a vibrant and modern interface</p>
                        </div>
                        <div className="p-8">
                            {/* Stats Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                                <div className="bg-gradient-to-br from-primary to-primary-dark text-white p-6 rounded-2xl shadow-2xl shadow-primary/25 hover:shadow-primary/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Students</h3>
                                            <p className="text-3xl font-bold text-white">{stats.total_students}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">👨‍🎓</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-light-bg to-primary-accent text-primary p-6 rounded-2xl shadow-2xl shadow-primary/20 hover:shadow-primary/40 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Teachers</h3>
                                            <p className="text-3xl font-bold">{stats.total_teachers}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">👨‍🏫</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-success via-primary-accent to-primary text-white p-6 rounded-2xl shadow-2xl shadow-success/25 hover:shadow-success/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Courses</h3>
                                            <p className="text-3xl font-bold">{stats.total_courses}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">📚</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-primary-dark to-primary text-white p-6 rounded-2xl shadow-2xl shadow-primary-dark/25 hover:shadow-primary-dark/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Schedules</h3>
                                            <p className="text-3xl font-bold">{stats.total_schedules}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">📅</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-primary to-light-accent text-primary p-6 rounded-2xl shadow-2xl shadow-primary/25 hover:shadow-primary/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Pending Credentials</h3>
                                            <p className="text-3xl font-bold">{stats.pending_credentials || 0}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">🔑</div>
                                    </div>
                                </div>
                            </div>

                            {/* Recent Schedules */}
                            <div className="mb-10">
                                <h3 className="text-2xl font-bold mb-6 text-primary">Recent Schedules</h3>
                                <div className="bg-white border border-primary/20 rounded-2xl overflow-hidden shadow-2xl shadow-primary/25">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-primary/20">
                                            <thead className="bg-gradient-to-r from-primary to-primary-dark text-white">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Course</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Teacher</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Room</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Day</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Time</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {recentSchedules?.length > 0 ? recentSchedules.map((schedule) => (
                                                    <tr key={schedule.id} className="hover:bg-gray-50 transition-colors duration-200">
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm font-semibold text-primary">
                                                                {schedule.course ? schedule.course.course_code : 'Not assigned'}
                                                            </div>
                                                            <div className="text-xs text-gray-500">
                                                                {schedule.course ? schedule.course.course_name : 'Not assigned'}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                            {schedule.teacher?.user?.name || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                            {schedule.room?.room_code || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                            {schedule.timeslot?.day_of_week || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                            {schedule.timeslot ? `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <Link
                                                                href={`/schedules/${schedule.id}`}
                                                                className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-light-bg text-primary hover:bg-primary hover:text-white transition-colors duration-200"
                                                            >
                                                                View
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
                                <h3 className="text-2xl font-bold mb-6 text-primary">Quick Actions</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                                    <Link
                                        href="/admin/users/create"
                                        className="group bg-gradient-to-r from-primary to-light-bg text-primary p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">👤</div>
                                            <h4 className="text-lg font-semibold mb-2">Create User</h4>
                                            <p className="text-primary/80 text-sm">Add new students, teachers, or admin users</p>
                                        </div>
                                    </Link>
                                    <Link
                                        href="/schedules/generate"
                                        className="group bg-gradient-to-r from-success to-primary text-white p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">⚡</div>
                                            <h4 className="text-lg font-semibold mb-2">Generate Schedule</h4>
                                            <p className="text-white/90 text-sm">Automatically create schedules for courses</p>
                                        </div>
                                    </Link>
                                    <Link
                                        href="/import"
                                        className="group bg-gradient-to-r from-light-accent to-primary text-primary p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">📥</div>
                                            <h4 className="text-lg font-semibold mb-2">Import Data</h4>
                                            <p className="text-primary/80 text-sm">Upload Excel files to import data</p>
                                        </div>
                                    </Link>
                                    <a
                                        href="/export/schedule"
                                        className="group bg-gradient-to-r from-primary-dark to-success text-white p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 block"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">📤</div>
                                            <h4 className="text-lg font-semibold mb-2">Export Schedule</h4>
                                            <p className="text-white/90 text-sm">Download schedules as Excel file</p>
                                        </div>
                                    </a>
                                    <a
                                        href="/export/credentials"
                                        className={`group bg-gradient-to-r from-primary to-success text-white p-6 rounded-2xl shadow-lg transition-all duration-300 hover:scale-105 block ${stats.pending_credentials <= 0 ? 'opacity-50 pointer-events-none' : 'hover:shadow-2xl'}`}
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">🔑</div>
                                            <h4 className="text-lg font-semibold mb-2">Export Credentials</h4>
                                            <p className="text-white/90 text-sm">
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

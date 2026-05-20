import React from 'react';
import { Link } from '@inertiajs/react';

export default function SchedulerDashboard({ stats, recentSchedules }) {
    return (
        <>
            <div className="py-12 bg-rich-black bg-opacity-90 min-h-screen" style={{ backgroundImage: 'linear-gradient(135deg, rgba(0,23,34,0.9) 0%, rgba(8,74,72,0.75) 50%, rgba(254,88,11,0.65) 100%)' }}>
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-slate-950/80 backdrop-blur-md overflow-hidden shadow-2xl sm:rounded-2xl border border-deep-jungle-green/40">
                        <div className="p-8 bg-gradient-to-r from-deep-jungle-green to-rich-black text-pearl-aqua">
                            <h2 className="text-3xl font-extrabold mb-2">Scheduler Dashboard</h2>
                            <p className="text-pearl-aqua/90">Generate and manage schedules with powerful scheduling tools</p>
                        </div>
                        <div className="p-8">
                            {/* Stats Cards */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                                <div className="bg-gradient-to-br from-deep-jungle-green to-rich-black text-white p-6 rounded-2xl shadow-2xl shadow-deep-jungle-green/25 hover:shadow-deep-jungle-green/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Total Schedules</h3>
                                            <p className="text-3xl font-bold text-pearl-aqua">{stats.total_schedules}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">??</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-pearl-aqua to-deep-jungle-green text-rich-black p-6 rounded-2xl shadow-2xl shadow-pearl-aqua/20 hover:shadow-pearl-aqua/40 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Pending</h3>
                                            <p className="text-3xl font-bold">{recentSchedules?.filter(s => s.status === 'pending').length || 0}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">?</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-vivid-orange via-pearl-aqua to-deep-jungle-green text-white p-6 rounded-2xl shadow-2xl shadow-vivid-orange/25 hover:shadow-vivid-orange/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">Active</h3>
                                            <p className="text-3xl font-bold">{recentSchedules?.filter(s => s.status === 'active').length || 0}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">?</div>
                                    </div>
                                </div>
                                <div className="bg-gradient-to-br from-rich-black to-deep-jungle-green text-white p-6 rounded-2xl shadow-2xl shadow-rich-black/25 hover:shadow-rich-black/45 transition-shadow duration-300">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold mb-2">This Week</h3>
                                            <p className="text-3xl font-bold">{recentSchedules?.filter(s => {
                                                if (!s.timeslot) return false;
                                                const scheduleDate = new Date();
                                                const dayOfWeek = s.timeslot.day_of_week;
                                                const dayMap = {'Monday': 1, 'Tuesday': 2, 'Wednesday': 3, 'Thursday': 4, 'Friday': 5, 'Saturday': 6, 'Sunday': 0};
                                                const scheduleDay = dayMap[dayOfWeek] || 0;
                                                const currentDay = scheduleDate.getDay();
                                                return Math.abs(currentDay - scheduleDay) <= 3;
                                            }).length || 0}</p>
                                        </div>
                                        <div className="text-4xl opacity-90">??</div>
                                    </div>
                                </div>
                            </div>

                            {/* Recent Schedules */}
                            <div className="mb-10">
                                <h3 className="text-2xl font-bold mb-6 text-pearl-aqua">Recent Schedules</h3>
                                <div className="bg-slate-900/80 border border-deep-jungle-green/40 rounded-2xl overflow-hidden shadow-2xl shadow-deep-jungle-green/25">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-deep-jungle-green/40">
                                            <thead className="bg-gradient-to-r from-deep-jungle-green/85 to-rich-black/85 text-white">
                                                <tr>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Course</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Teacher</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Room</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Day</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Time</th>
                                                    <th className="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-slate-950/80 divide-y divide-deep-jungle-green/30">
                                                {recentSchedules?.length > 0 ? recentSchedules.map((schedule) => (
                                                    <tr key={schedule.id} className="hover:bg-slate-800 transition-colors duration-200">
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm font-semibold text-pearl-aqua">
                                                                {schedule.course ? schedule.course.course_code : 'Not assigned'}
                                                            </div>
                                                            <div className="text-xs text-slate-300">
                                                                {schedule.course ? schedule.course.course_name : 'Not assigned'}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-200">
                                                            {schedule.teacher?.user?.name || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-200">
                                                            {schedule.room?.room_code || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-200">
                                                            {schedule.timeslot?.day_of_week || 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-200">
                                                            {schedule.timeslot ? `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : 'Not assigned'}
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <Link
                                                                href={`/schedules/${schedule.id}`}
                                                                className="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-pearl-aqua text-rich-black hover:bg-vivid-orange hover:text-white transition-colors duration-200"
                                                            >
                                                                View
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                )) : (
                                                    <tr>
                                                        <td colSpan="6" className="px-6 py-8 text-center text-slate-400">
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
                                <h3 className="text-2xl font-bold mb-6 text-pearl-aqua">Quick Actions</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                                    <Link
                                        href={route('schedules.generate')}
                                        className="group bg-gradient-to-r from-vivid-orange to-deep-jungle-green text-white p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">?</div>
                                            <h4 className="text-lg font-semibold mb-2">Generate Schedule</h4>
                                            <p className="text-pearl-aqua/90 text-sm">Automatically create schedules for courses</p>
                                        </div>
                                    </Link>
                                    <Link
                                        href={route('import.index')}
                                        className="group bg-gradient-to-r from-pearl-aqua to-deep-jungle-green text-rich-black p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">??</div>
                                            <h4 className="text-lg font-semibold mb-2">Import Data</h4>
                                            <p className="text-rich-black/80 text-sm">Upload Excel files to import data</p>
                                        </div>
                                    </Link>
                                    <a
                                        href={route('export.schedule')}
                                        className="group bg-gradient-to-r from-deep-jungle-green to-vivid-orange text-white p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 block"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">??</div>
                                            <h4 className="text-lg font-semibold mb-2">Export Schedule</h4>
                                            <p className="text-pearl-aqua/90 text-sm">Download schedules as Excel file</p>
                                        </div>
                                    </a>
                                    <Link
                                        href="/schedules"
                                        className="group bg-gradient-to-r from-deep-jungle-green to-pearl-aqua text-rich-black p-6 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 hover:scale-105"
                                    >
                                        <div className="text-center">
                                            <div className="text-4xl mb-3">??</div>
                                            <h4 className="text-lg font-semibold mb-2">Manage Schedules</h4>
                                            <p className="text-rich-black/80 text-sm">View and edit existing schedules</p>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

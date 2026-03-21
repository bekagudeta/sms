import React from 'react';
import { Link } from '@inertiajs/react';

export default function SchedulerDashboard({ stats, recentSchedules }) {
    return (
        <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
            <div className="p-8 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                <h2 className="text-3xl font-bold mb-2">Scheduler Dashboard</h2>
                <p className="text-blue-100">Generate and manage schedules</p>
            </div>

            <div className="p-8">
                {/* Optionally show stats (e.g. total schedules) */}
                {stats.total_schedules !== undefined && (
                    <div className="mb-10">
                        <h3 className="text-2xl font-bold mb-4 text-gray-800">Overview</h3>
                        <div className="bg-gradient-to-br from-purple-500 to-pink-500 text-white p-6 rounded-xl shadow-lg">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-lg font-semibold mb-2">Total Schedules</h3>
                                    <p className="text-3xl font-bold">{stats.total_schedules}</p>
                                </div>
                                <div className="text-4xl opacity-80">📅</div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Quick actions for scheduler */}
                <div className="mb-10">
                    <h3 className="text-2xl font-bold mb-6 text-gray-800">Quick Actions</h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <Link
                            href={route('schedules.generate')}
                            className="group bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                        >
                            <div className="text-center">
                                <div className="text-4xl mb-3">⚡</div>
                                <h4 className="text-lg font-semibold mb-2">Generate Schedule</h4>
                                <p className="text-indigo-100 text-sm">Create schedules automatically</p>
                            </div>
                        </Link>
                        <Link
                            href={route('import.index')}
                            className="group bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105"
                        >
                            <div className="text-center">
                                <div className="text-4xl mb-3">📥</div>
                                <h4 className="text-lg font-semibold mb-2">Import Data</h4>
                                <p className="text-green-100 text-sm">Upload Excel files</p>
                            </div>
                        </Link>
                        <a
                            href={route('export.schedule')}
                            className="group bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 block"
                        >
                            <div className="text-center">
                                <div className="text-4xl mb-3">📤</div>
                                <h4 className="text-lg font-semibold mb-2">Export Schedule</h4>
                                <p className="text-purple-100 text-sm">Download schedules</p>
                            </div>
                        </a>
                    </div>
                </div>

                {/* Recent schedules list */}
                <div className="mb-10">
                    <h3 className="text-2xl font-bold mb-6 text-gray-800">Recent Schedules</h3>
                    {recentSchedules.length > 0 ? (
                        <ul className="space-y-2">
                            {recentSchedules.map(s => (
                                <li key={s.id} className="p-4 border rounded-lg flex justify-between items-center">
                                    <span>{s.course.course_code} - {s.course.course_name}</span>
                                    <Link href={`/schedules/${s.id}`} className="text-blue-600 hover:underline">View</Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-gray-600">No schedules available yet.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

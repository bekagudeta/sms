import React from 'react';
import { Link } from '@inertiajs/react';

export default function TeacherDashboard({ recentSchedules }) {
    return (
        <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
            <div className="p-8 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                <h2 className="text-3xl font-bold mb-2">Teacher Dashboard</h2>
                <p className="text-blue-100">Here are your upcoming classes</p>
            </div>

            <div className="p-8">
                <div className="mb-10">
                    <h3 className="text-2xl font-bold mb-6 text-gray-800">My Schedules</h3>
                    {recentSchedules.length > 0 ? (
                        <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-lg">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Course</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Room</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Day</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                                            <th className="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {recentSchedules.map(schedule => (
                                            <tr key={schedule.id} className="hover:bg-gray-50 transition-colors duration-200">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {schedule.course ? schedule.course.course_code : 'Not assigned'}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {schedule.course ? schedule.course.course_name : 'Not assigned'}
                                                    </div>
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
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <p className="text-gray-600">You don't have any assigned schedules yet.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

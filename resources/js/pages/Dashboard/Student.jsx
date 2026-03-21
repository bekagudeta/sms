import React from 'react';
import { Link } from '@inertiajs/react';

export default function StudentDashboard({ recentSchedules }) {
    return (
        <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
            <div className="p-8 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                <h2 className="text-3xl font-bold mb-2">Student Dashboard</h2>
                <p className="text-blue-100">Check your class schedule and stay on top of your studies</p>
            </div>

            <div className="p-8">
                <div className="mb-10">
                    <h3 className="text-2xl font-bold mb-6 text-gray-800">Upcoming Classes</h3>
                    {recentSchedules.length > 0 ? (
                        <ul className="space-y-2">
                            {recentSchedules.map(s => (
                                <li key={s.id} className="p-4 border rounded-lg flex justify-between items-center">
                                    <div>
                                        <strong>{s.course.course_code}</strong> - {s.course.course_name}
                                        <div className="text-sm text-gray-500">
                                            {s.timeslot?.day_of_week || 'TBA'}{' '}
                                            {s.timeslot ? `${s.timeslot.start_time} - ${s.timeslot.end_time}` : ''}
                                        </div>
                                    </div>
                                    <Link href={`/schedules/${s.id}`} className="text-blue-600 hover:underline">View details</Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-gray-600">No classes scheduled for you yet.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

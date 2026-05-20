import React from 'react';
import { Link } from '@inertiajs/react';

export default function TeacherDashboard({ recentSchedules }) {
    return (
        <div className="app-panel overflow-hidden">
            <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">Teaching overview</p>
                <h2 className="text-3xl font-bold">Teacher Dashboard</h2>
                <p className="mt-2 text-platinum/80">Review your assigned classes with a cleaner, easier-to-scan schedule table.</p>
            </div>

            <div className="p-8">
                <div className="mb-6 flex items-center justify-between">
                    <h3 className="text-2xl font-bold text-deep-jungle-green">My Schedules</h3>
                    <span className="app-badge">{recentSchedules.length} classes</span>
                </div>

                {recentSchedules.length > 0 ? (
                    <div className="app-table-shell">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-deep-jungle-green/10">
                                <thead className="bg-deep-jungle-green text-platinum">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Course</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Room</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Day</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Time</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-deep-jungle-green/10 bg-white">
                                    {recentSchedules.map((schedule, index) => (
                                        <tr key={schedule.id} className={index % 2 === 0 ? 'bg-white' : 'bg-platinum/40'}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">
                                                <div className="font-semibold">{schedule.course ? schedule.course.course_code : 'Not assigned'}</div>
                                                <div className="text-deep-jungle-green/65">{schedule.course ? schedule.course.course_name : 'Not assigned'}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{schedule.room?.room_code || 'Not assigned'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{schedule.timeslot?.day_of_week || 'Not assigned'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">
                                                {schedule.timeslot ? `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : 'Not assigned'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Link href={`/schedules/${schedule.id}`} className="app-secondary-btn !px-3 !py-1.5">
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
                    <div className="app-panel-muted px-6 py-8 text-center text-deep-jungle-green/70">
                        You do not have any assigned schedules yet.
                    </div>
                )}
            </div>
        </div>
    );
}

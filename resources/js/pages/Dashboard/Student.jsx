import React from 'react';
import { Link } from '@inertiajs/react';

export default function StudentDashboard({ recentSchedules }) {
    return (
        <div className="app-panel overflow-hidden">
            <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">Student focus</p>
                <h2 className="text-3xl font-bold">Student Dashboard</h2>
                <p className="mt-2 text-platinum/80">Keep upcoming classes easy to read, clear, and action-focused.</p>
            </div>

            <div className="p-8">
                <div className="mb-6 flex items-center justify-between">
                    <h3 className="text-2xl font-bold text-deep-jungle-green">Upcoming Classes</h3>
                    <span className="app-badge">{recentSchedules.length} items</span>
                </div>

                {recentSchedules.length > 0 ? (
                    <ul className="space-y-3">
                        {recentSchedules.map((schedule) => (
                            <li key={schedule.id} className="app-panel-muted flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="text-base font-semibold text-deep-jungle-green">
                                        {schedule.course ? schedule.course.course_code : 'Not assigned'}
                                        <span className="ml-2 font-normal text-deep-jungle-green/70">{schedule.course ? schedule.course.course_name : 'Not assigned'}</span>
                                    </div>
                                    <div className="mt-1 text-sm text-deep-jungle-green/65">
                                        {schedule.timeslot?.day_of_week || 'TBA'} {schedule.timeslot ? `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : ''}
                                    </div>
                                </div>
                                <Link href={`/schedules/${schedule.id}`} className="app-primary-btn !px-3 !py-2">
                                    View details
                                </Link>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="app-panel-muted px-6 py-8 text-center text-deep-jungle-green/70">
                        No classes are scheduled for you yet.
                    </div>
                )}
            </div>
        </div>
    );
}

import React from 'react';
import { Link } from '@inertiajs/react';

export default function StudentDashboard({ recentSchedules, studentProfile, enrolledCourses = [] }) {
    return (
        <div className="app-panel overflow-hidden">
            <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">Student focus</p>
                <h2 className="text-3xl font-bold">Student Dashboard</h2>
                <p className="mt-2 text-platinum/80">Your academic cohort and enrolled course sections.</p>
            </div>

            <div className="grid gap-6 p-8 lg:grid-cols-2">
                <div className="app-panel-muted p-6">
                    <h3 className="text-lg font-semibold text-deep-jungle-green">Profile</h3>
                    {studentProfile ? (
                        <dl className="mt-4 space-y-2 text-sm text-deep-jungle-green/80">
                            <div>
                                <dt className="font-medium text-deep-jungle-green">Name</dt>
                                <dd>{studentProfile.full_name}</dd>
                            </div>
                            <div>
                                <dt className="font-medium text-deep-jungle-green">Student ID</dt>
                                <dd>{studentProfile.student_id}</dd>
                            </div>
                            <div>
                                <dt className="font-medium text-deep-jungle-green">Academic Section</dt>
                                <dd>{studentProfile.academic_section || 'Not assigned'}</dd>
                            </div>
                            {studentProfile.department && (
                                <div>
                                    <dt className="font-medium text-deep-jungle-green">Department</dt>
                                    <dd>{studentProfile.department}</dd>
                                </div>
                            )}
                        </dl>
                    ) : (
                        <p className="mt-4 text-sm text-deep-jungle-green/70">Student profile not linked to this account.</p>
                    )}
                </div>

                <div className="app-panel-muted p-6">
                    <h3 className="text-lg font-semibold text-deep-jungle-green">Current Courses</h3>
                    <p className="mt-1 text-xs text-deep-jungle-green/60">Course sections you are enrolled in (from Enrollments import).</p>
                    {enrolledCourses.length > 0 ? (
                        <ul className="mt-4 divide-y divide-deep-jungle-green/10">
                            {enrolledCourses.map((course, index) => (
                                <li key={`${course.course_code}-${course.section_name}-${index}`} className="flex justify-between gap-4 py-3 text-sm">
                                    <span className="font-medium text-deep-jungle-green">
                                        {course.course_name || course.course_code}
                                    </span>
                                    <span className="text-deep-jungle-green/70">
                                        {course.course_code} · {course.section_name}
                                        {course.semester ? ` · ${course.semester}` : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 text-sm text-deep-jungle-green/70">
                            No course enrollments yet. Import enrollments after students and course sections.
                        </p>
                    )}
                </div>
            </div>

            <div className="border-t border-deep-jungle-green/10 px-8 pb-8">
                <div className="mb-6 flex items-center justify-between pt-6">
                    <h3 className="text-2xl font-bold text-deep-jungle-green">Upcoming Classes</h3>
                    <span className="app-badge">{recentSchedules.length} items</span>
                </div>

                {recentSchedules.length > 0 ? (
                    <ul className="space-y-3">
                        {recentSchedules.map((schedule) => (
                            <li key={schedule.id} className="app-panel-muted flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="text-base font-semibold text-deep-jungle-green">
                                        {schedule.section?.course_offering?.course?.course_code || schedule.course?.course_code || 'Course'}
                                        <span className="ml-2 font-normal text-deep-jungle-green/70">
                                            {schedule.section?.course_offering?.course?.course_name || schedule.course?.course_name || ''}
                                        </span>
                                    </div>
                                    <div className="mt-1 text-sm text-deep-jungle-green/65">
                                        Section {schedule.section?.section_name || '—'} ·{' '}
                                        {schedule.timeslot?.day_of_week || 'TBA'}{' '}
                                        {schedule.timeslot ? `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : ''}
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
                        No scheduled classes for your enrolled course sections yet.
                    </div>
                )}
            </div>
        </div>
    );
}

import React from 'react';
import { Link } from '@inertiajs/react';
import ScheduleTable from '@/Components/ScheduleTable';

const PREVIEW_STUDENT_LIMIT = 5;

export default function TeacherDashboard({ recentSchedules, myStudents = [], activeSemester }) {
    const exportUrl = activeSemester?.id
        ? `/export/teacher-students?semester_id=${activeSemester.id}`
        : '/export/teacher-students';

    return (
        <div className="space-y-8">
            <div className="app-panel overflow-hidden">
                <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">Teaching overview</p>
                    <h2 className="text-3xl font-bold">Teacher Dashboard</h2>
                    <p className="mt-2 text-platinum/80">
                        Your class timetable and enrolled students
                        {activeSemester ? ` — ${activeSemester.name} (${activeSemester.academic_year ?? 'academic year n/a'})` : ''}.
                    </p>
                </div>

                <div className="p-8">
                    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                        <h3 className="text-2xl font-bold text-deep-jungle-green">My Schedule</h3>
                        <div className="flex items-center gap-3">
                            <span className="app-badge">{recentSchedules.length} classes</span>
                            <Link href="/schedules" className="app-secondary-btn">
                                View all
                            </Link>
                        </div>
                    </div>

                    {recentSchedules.length > 0 ? (
                        <ScheduleTable schedules={recentSchedules} />
                    ) : (
                        <div className="app-panel-muted px-6 py-8 text-center text-deep-jungle-green/70">
                            You do not have any assigned schedules yet.
                        </div>
                    )}
                </div>
            </div>

            <div className="app-panel overflow-hidden">
                <div className="border-b border-deep-jungle-green/10 px-8 py-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 className="text-2xl font-bold text-deep-jungle-green">My Students</h3>
                            <p className="mt-1 text-sm text-deep-jungle-green/70">
                                Preview of students in your sections. Open the full roster from the sidebar or below.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Link href="/teacher/students" className="app-secondary-btn">
                                View all students
                            </Link>
                            <a href={exportUrl} className="app-primary-btn">
                                Export to Excel
                            </a>
                        </div>
                    </div>
                </div>

                <div className="p-8">
                    {myStudents.length > 0 ? (
                        <>
                        <div className="app-table-shell overflow-x-auto">
                            <table className="min-w-full divide-y divide-deep-jungle-green/10">
                                <thead className="bg-deep-jungle-green text-platinum">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Student ID</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Name</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Department</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Year Level</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Cohort</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Email</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Your Courses</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-deep-jungle-green/10 bg-white">
                                    {myStudents.slice(0, PREVIEW_STUDENT_LIMIT).map((student, index) => (
                                        <tr key={student.id} className={index % 2 === 0 ? 'bg-white' : 'bg-platinum/40'}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-deep-jungle-green">{student.student_id}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.full_name}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.department ?? '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.year_level ?? '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.academic_section ?? '—'}</td>
                                            <td className="px-6 py-4 text-sm text-deep-jungle-green">{student.email}</td>
                                            <td className="px-6 py-4 text-sm text-deep-jungle-green">
                                                {(student.courses ?? []).map((c) => c.course_code).filter(Boolean).join(', ') || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {myStudents.length > PREVIEW_STUDENT_LIMIT && (
                            <p className="mt-4 text-center text-sm text-deep-jungle-green/70">
                                Showing {PREVIEW_STUDENT_LIMIT} of {myStudents.length} students.{' '}
                                <Link href="/teacher/students" className="font-semibold text-deep-jungle-green underline-offset-2 hover:underline">
                                    View full roster
                                </Link>
                            </p>
                        )}
                        </>
                    ) : (
                        <div className="app-panel-muted px-6 py-8 text-center text-deep-jungle-green/70">
                            No students are enrolled in your sections yet.
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

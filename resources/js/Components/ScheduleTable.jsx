import React from 'react';

const HEADERS = [
    { key: 'academic_year', label: 'Academic Year' },
    { key: 'department', label: 'Department' },
    { key: 'year_level', label: 'Year Level' },
    { key: 'semester', label: 'Semester' },
    { key: 'course', label: 'Course' },
    { key: 'instructor', label: 'Instructor' },
    { key: 'classroom', label: 'Classroom' },
    { key: 'day', label: 'Day' },
    { key: 'time', label: 'Time' },
];

function rowValues(schedule) {
    const display = schedule.display ?? {};
    const course = schedule.course ?? schedule.section?.course_offering?.course;

    return {
        academic_year: schedule.academic_year ?? display.academic_year ?? '—',
        department: schedule.department_name ?? display.department ?? course?.department?.name ?? '—',
        year_level: schedule.year_level ?? display.year_level ?? '—',
        semester: schedule.semester?.name ?? display.semester ?? '—',
        course: course
            ? `${course.course_code} — ${course.course_name}`
            : display.course_code
              ? `${display.course_code} — ${display.course_name}`
              : 'Not assigned',
        instructor: schedule.teacher_name ?? display.instructor ?? 'Not assigned',
        classroom: schedule.room?.room_code ?? display.classroom ?? '—',
        day: schedule.timeslot?.day_of_week ?? display.day ?? '—',
        time: schedule.time_range ?? display.time ?? '—',
    };
}

export function ScheduleTableHead({ compact = false, actionColumn = false }) {
    return (
        <thead className="bg-deep-jungle-green text-platinum">
            <tr>
                {HEADERS.map((h) => (
                    <th
                        key={h.key}
                        className={`text-left text-xs font-semibold uppercase tracking-wider ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}
                    >
                        {h.label}
                    </th>
                ))}
                {actionColumn && (
                    <th className={`text-left text-xs font-semibold uppercase tracking-wider ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}>
                        Action
                    </th>
                )}
            </tr>
        </thead>
    );
}

export function ScheduleTableBody({ schedules, compact = false, actionColumn = null }) {
    return (
        <tbody className="divide-y divide-deep-jungle-green/10 bg-white">
            {schedules.map((schedule, index) => {
                const values = rowValues(schedule);
                return (
                    <tr key={schedule.id} className={index % 2 === 0 ? 'bg-white' : 'bg-platinum/40'}>
                        {HEADERS.map((h) => (
                            <td
                                key={h.key}
                                className={`text-sm text-deep-jungle-green ${h.key === 'course' ? '' : 'whitespace-nowrap'} ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}
                            >
                                {h.key === 'course' ? (
                                    <span className="font-semibold">{values.course}</span>
                                ) : (
                                    values[h.key]
                                )}
                            </td>
                        ))}
                        {actionColumn && (
                            <td className={`whitespace-nowrap ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}>
                                {actionColumn(schedule)}
                            </td>
                        )}
                    </tr>
                );
            })}
        </tbody>
    );
}

export default function ScheduleTable({ schedules, compact = false, actionColumn = null, headerClass = 'bg-deep-jungle-green text-platinum' }) {
    return (
        <div className="app-table-shell overflow-x-auto">
            <table className="min-w-full divide-y divide-deep-jungle-green/10">
                <thead className={headerClass}>
                    <tr>
                        {HEADERS.map((h) => (
                            <th
                                key={h.key}
                                className={`text-left text-xs font-semibold uppercase tracking-wider ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}
                            >
                                {h.label}
                            </th>
                        ))}
                        {actionColumn && (
                            <th className={`text-left text-xs font-semibold uppercase tracking-wider ${compact ? 'px-4 py-3' : 'px-6 py-4'}`}>
                                Action
                            </th>
                        )}
                    </tr>
                </thead>
                <ScheduleTableBody schedules={schedules} compact={compact} actionColumn={actionColumn} />
            </table>
        </div>
    );
}

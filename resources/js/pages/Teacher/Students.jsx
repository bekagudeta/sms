import React, { useMemo, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { router } from '@inertiajs/react';

export default function TeacherStudents({ students = [], semesters = [], currentSemesterId, currentSemester }) {
    const [search, setSearch] = useState('');

    const exportUrl = currentSemesterId
        ? `/export/teacher-students?semester_id=${currentSemesterId}`
        : '/export/teacher-students';

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) {
            return students;
        }

        return students.filter((student) => {
            const haystack = [
                student.student_id,
                student.full_name,
                student.email,
                student.department,
                student.year_level,
                student.academic_section,
                (student.courses ?? []).map((c) => c.course_code).join(' '),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return haystack.includes(term);
        });
    }, [students, search]);

    const changeSemester = (event) => {
        const semesterId = event.target.value;
        router.get(
            '/teacher/students',
            semesterId ? { semester_id: semesterId } : {},
            { preserveState: true, replace: true }
        );
    };

    return (
        <DashboardLayout>
            <div className="app-panel overflow-hidden">
                <div className="border-b border-deep-jungle-green/10 bg-deep-jungle-green px-8 py-7 text-platinum">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-widest text-vivid-orange">Class roster</p>
                    <h2 className="text-3xl font-bold">My Students</h2>
                    <p className="mt-2 text-platinum/80">
                        Students enrolled in your sections
                        {currentSemester
                            ? ` — ${currentSemester.name} (${currentSemester.academic_year ?? 'academic year n/a'})`
                            : ''}
                        .
                    </p>
                </div>

                <div className="space-y-6 p-8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div>
                                <label htmlFor="semester" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-deep-jungle-green/70">
                                    Semester
                                </label>
                                <select
                                    id="semester"
                                    value={currentSemesterId ?? ''}
                                    onChange={changeSemester}
                                    className="min-w-[220px] rounded-lg border border-deep-jungle-green/20 px-3 py-2 text-sm text-deep-jungle-green focus:border-deep-jungle-green focus:outline-none focus:ring-1 focus:ring-deep-jungle-green"
                                >
                                    <option value="">All semesters</option>
                                    {semesters.map((semester) => (
                                        <option key={semester.id} value={semester.id}>
                                            {semester.name}
                                            {semester.academic_year ? ` (${semester.academic_year})` : ''}
                                            {semester.is_active ? ' — Active' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="sm:pt-5">
                                <input
                                    type="search"
                                    placeholder="Search by name, ID, email, cohort..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full min-w-[260px] rounded-lg border border-deep-jungle-green/20 px-3 py-2 text-sm text-deep-jungle-green placeholder:text-deep-jungle-green/45 focus:border-deep-jungle-green focus:outline-none focus:ring-1 focus:ring-deep-jungle-green sm:w-80"
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="app-badge">{filtered.length} students</span>
                            <a href={exportUrl} className="app-primary-btn whitespace-nowrap">
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    {filtered.length > 0 ? (
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
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Phone</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Your Courses</th>
                                        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-deep-jungle-green/10 bg-white">
                                    {filtered.map((student, index) => (
                                        <tr key={student.id} className={index % 2 === 0 ? 'bg-white' : 'bg-platinum/40'}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-deep-jungle-green">{student.student_id}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.full_name}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.department ?? '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.year_level ?? '—'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.academic_section ?? '—'}</td>
                                            <td className="px-6 py-4 text-sm text-deep-jungle-green">
                                                <a href={`mailto:${student.email}`} className="text-deep-jungle-green underline-offset-2 hover:underline">
                                                    {student.email}
                                                </a>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-deep-jungle-green">{student.phone || '—'}</td>
                                            <td className="px-6 py-4 text-sm text-deep-jungle-green">
                                                {(student.courses ?? []).map((c) => c.course_code).filter(Boolean).join(', ') || '—'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm capitalize text-deep-jungle-green">{student.status ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="app-panel-muted px-6 py-12 text-center text-deep-jungle-green/70">
                            {search
                                ? 'No students match your search.'
                                : 'No students are enrolled in your sections for this semester.'}
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

import React, { useMemo, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

function getSectionSemesterId(section) {
    return Number(section?.course_offering?.semester_id ?? section?.courseOffering?.semester_id ?? 0);
}

function StatCard({ title, value, subtitle }) {
    return (
        <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p className="text-sm font-medium text-gray-600">{title}</p>
            <p className="mt-2 text-2xl font-bold text-gray-900">{value}</p>
            {subtitle && <p className="mt-1 text-xs text-gray-500">{subtitle}</p>}
        </div>
    );
}

function getOfferingSemesterId(offering) {
    return Number(offering?.semester_id ?? offering?.semester?.id ?? 0);
}

function getSectionCourseOfferingId(section) {
    return Number(section?.course_offering_id ?? section?.course_offering?.id ?? section?.courseOffering?.id ?? 0);
}

export default function Generate({ semesters = [], courseOfferings = [], teachers = [], rooms = [], timeslots = [], sections = [] }) {
    const [activeTab, setActiveTab] = useState('auto');
    const { props } = usePage();
    const flash = props.flash;

    const { data: autoData, setData: setAutoData, post: autoPost, processing: autoProcessing } = useForm({
        semester_id: ''
    });

    const selectedSemesterId = Number(autoData.semester_id || 0);
    const selectedSemester = semesters.find((semester) => Number(semester.id) === selectedSemesterId);

    const semesterCourseOfferings = useMemo(() => {
        if (!selectedSemesterId) return [];
        return courseOfferings.filter((offering) => Number(offering.semester_id ?? offering.semester?.id) === selectedSemesterId);
    }, [courseOfferings, selectedSemesterId]);

    const semesterSections = useMemo(() => {
        if (!selectedSemesterId) return [];
        return sections.filter((section) => getSectionSemesterId(section) === selectedSemesterId);
    }, [sections, selectedSemesterId]);

    const sectionsWithoutTeachers = useMemo(() => {
        return semesterSections.filter((section) => !Array.isArray(section.teachers) || section.teachers.length === 0);
    }, [semesterSections]);

    const sectionsWithoutEnrollments = useMemo(() => {
        return semesterSections.filter((section) => !Array.isArray(section.enrollments) || section.enrollments.length === 0);
    }, [semesterSections]);

    const totalSemesterEnrollments = useMemo(() => {
        return semesterSections.reduce((sum, section) => sum + (section.enrollments?.length ?? 0), 0);
    }, [semesterSections]);

    const readinessChecks = [
        {
            label: 'Semester selected',
            ok: Boolean(selectedSemesterId),
            blocking: true,
            detail: 'Choose which semester should be scheduled.'
        },
        {
            label: 'Course offerings available',
            ok: semesterCourseOfferings.length > 0,
            blocking: true,
            detail: 'The selected semester needs at least one course offering.'
        },
        {
            label: 'Sections available',
            ok: semesterSections.length > 0,
            blocking: true,
            detail: 'The scheduler creates timetables for sections.'
        },
        {
            label: 'Every section has a teacher',
            ok: semesterSections.length > 0 && sectionsWithoutTeachers.length === 0,
            blocking: true,
            detail: 'Automatic generation requires at least one teacher per section.'
        },
        {
            label: 'Rooms loaded',
            ok: rooms.length > 0,
            blocking: true,
            detail: 'Room capacity and type are enforced during scheduling.'
        },
        {
            label: 'Timeslots loaded',
            ok: timeslots.length > 0,
            blocking: true,
            detail: 'Real timeslots should exist before generating schedules.'
        },
        {
            label: 'Enrollments loaded',
            ok: totalSemesterEnrollments > 0,
            blocking: false,
            detail: 'Recommended for student conflict detection.'
        }
    ];

    const blockers = readinessChecks.filter((check) => check.blocking && !check.ok);
    const canAutoGenerate = blockers.length === 0;

    const handleAutoGenerate = () => {
        if (!autoData.semester_id) {
            alert('Please select a semester for automatic generation.');
            return;
        }

        if (!canAutoGenerate) {
            alert(`Please complete setup first: ${blockers.map((item) => item.label).join(', ')}`);
            return;
        }

        autoPost(route('schedules.generate.auto'), {
            onError: (errors) => {
                console.error('Auto generation errors:', errors);
                alert('Error generating automatic schedule: ' + Object.values(errors).join(', '));
            }
        });
    };

    return (
        <DashboardLayout>
            <Head title="Generate Schedule" />

            <div className="space-y-6">
                <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div className="p-6 bg-white border-b border-gray-200">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">Schedule Generation</h2>
                                <p className="mt-2 text-sm text-gray-600">
                                    This generator schedules <span className="font-semibold">sections</span> using imported <span className="font-semibold">teachers</span>, <span className="font-semibold">rooms</span>, <span className="font-semibold">timeslots</span>, and optional <span className="font-semibold">enrollments</span> for student conflict prevention.
                                </p>
                            </div>

                            <div className="flex gap-3">
                                <Link
                                    href={route('import.index')}
                                    className="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                                >
                                    Open Setup Wizard
                                </Link>
                                <Link
                                    href={route('schedules.index')}
                                    className="rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                >
                                    View Schedules
                                </Link>
                            </div>
                        </div>

                        {flash?.success && (
                            <div className="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                                {flash.success}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                                {flash.error}
                            </div>
                        )}

                        <div className="mt-6 border-b border-gray-200">
                            <nav className="-mb-px flex space-x-8">
                                <button
                                    onClick={() => setActiveTab('auto')}
                                    className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                        activeTab === 'auto'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    Automatic Generation
                                </button>
                                <button
                                    onClick={() => setActiveTab('manual')}
                                    className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                        activeTab === 'manual'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    Manual Adjustment
                                </button>
                            </nav>
                        </div>

                        {activeTab === 'auto' && (
                            <div className="mt-6 space-y-6">
                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                        <h3 className="text-lg font-semibold text-blue-900">What the generator needs</h3>
                                        <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-blue-800">
                                            <li><span className="font-semibold">Course offerings</span> and <span className="font-semibold">sections</span> for the selected semester</li>
                                            <li><span className="font-semibold">Section-teacher assignments</span> so every section has an instructor</li>
                                            <li><span className="font-semibold">Rooms</span> with capacity/type and valid <span className="font-semibold">timeslots</span></li>
                                            <li><span className="font-semibold">Enrollments</span> if you want student conflict prevention</li>
                                        </ul>
                                    </div>

                                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                        <h3 className="text-lg font-semibold text-amber-900">Recommended workflow</h3>
                                        <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm text-amber-800">
                                            <li>Import setup data in the wizard.</li>
                                            <li>Pick a semester and review readiness below.</li>
                                            <li>Generate automatically for the first draft.</li>
                                            <li>Use the manual tab to fix edge cases.</li>
                                        </ol>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <label className="block text-sm font-medium text-gray-700">Semester *</label>
                                    <select
                                        value={autoData.semester_id}
                                        onChange={(e) => setAutoData('semester_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        required
                                    >
                                        <option value="">Select Semester</option>
                                        {semesters?.map((semester) => (
                                            <option key={semester.id} value={semester.id}>
                                                {semester.name}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="mt-2 text-xs text-gray-500">
                                        Existing schedules for the selected semester will be replaced when you generate automatically.
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                                    <StatCard title="Course Offerings" value={selectedSemesterId ? semesterCourseOfferings.length : '-'} subtitle="For selected semester" />
                                    <StatCard title="Sections" value={selectedSemesterId ? semesterSections.length : '-'} subtitle="Actual classes to schedule" />
                                    <StatCard title="Teachers Ready" value={selectedSemesterId ? `${semesterSections.length - sectionsWithoutTeachers.length}/${semesterSections.length || 0}` : '-'} subtitle="Sections with at least one teacher" />
                                    <StatCard title="Rooms" value={rooms.length} subtitle="Available teaching spaces" />
                                    <StatCard title="Timeslots" value={timeslots.length} subtitle="Loaded timetable slots" />
                                    <StatCard title="Enrollments" value={selectedSemesterId ? totalSemesterEnrollments : '-'} subtitle="Used for student conflict checks" />
                                </div>

                                <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900">Ready-to-generate checklist</h3>
                                            <p className="mt-1 text-sm text-gray-600">
                                                {selectedSemester ? `Review the current setup for ${selectedSemester.name}.` : 'Select a semester to evaluate scheduling readiness.'}
                                            </p>
                                        </div>
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${canAutoGenerate ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}>
                                            {canAutoGenerate ? 'Ready' : `${blockers.length} blocker${blockers.length === 1 ? '' : 's'}`}
                                        </span>
                                    </div>

                                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                                        {readinessChecks.map((check) => (
                                            <div key={check.label} className="rounded-md border border-gray-200 bg-gray-50 p-3">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium text-gray-900">{check.label}</p>
                                                    <span className={`rounded-full px-2 py-1 text-xs font-semibold ${check.ok ? 'bg-green-100 text-green-700' : check.blocking ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}`}>
                                                        {check.ok ? 'OK' : check.blocking ? 'Missing' : 'Recommended'}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-xs text-gray-600">{check.detail}</p>
                                            </div>
                                        ))}
                                    </div>

                                    {sectionsWithoutTeachers.length > 0 && (
                                        <div className="mt-4 rounded-md border border-red-200 bg-red-50 p-4">
                                            <p className="text-sm font-semibold text-red-800">Sections still missing teacher assignments</p>
                                            <p className="mt-1 text-xs text-red-700">
                                                Assign teachers to these sections before automatic generation.
                                            </p>
                                            <ul className="mt-2 list-disc pl-5 text-sm text-red-800">
                                                {sectionsWithoutTeachers.slice(0, 8).map((section) => (
                                                    <li key={section.id}>{section.section_name}</li>
                                                ))}
                                            </ul>
                                            {sectionsWithoutTeachers.length > 8 && (
                                                <p className="mt-2 text-xs text-red-700">...and {sectionsWithoutTeachers.length - 8} more section(s).</p>
                                            )}
                                        </div>
                                    )}

                                    {selectedSemesterId && sectionsWithoutEnrollments.length > 0 && (
                                        <div className="mt-4 rounded-md border border-yellow-200 bg-yellow-50 p-4">
                                            <p className="text-sm font-semibold text-yellow-800">Enrollment coverage is still incomplete</p>
                                            <p className="mt-1 text-xs text-yellow-700">
                                                Automatic generation can still run, but student conflict detection will be weaker for {sectionsWithoutEnrollments.length} section(s).
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <button
                                        onClick={handleAutoGenerate}
                                        disabled={autoProcessing || !canAutoGenerate}
                                        className="rounded-md bg-indigo-600 px-6 py-3 font-medium text-white transition-colors duration-200 hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                                    >
                                        {autoProcessing ? 'Generating...' : 'Generate Automatic Schedule'}
                                    </button>
                                    {!canAutoGenerate && (
                                        <p className="text-sm text-amber-700">
                                            Complete the blockers above before running the scheduler.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        {activeTab === 'manual' && (
                            <div className="mt-6">
                                <div className="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                                    <h3 className="text-lg font-semibold text-indigo-900">Manual adjustment mode</h3>
                                    <p className="mt-1 text-sm text-indigo-800">
                                        Use this after the automatic generator to fix edge cases, special room needs, or remaining conflicts one row at a time.
                                    </p>
                                </div>

                                <ManualScheduleForm
                                    semesters={semesters}
                                    courseOfferings={courseOfferings}
                                    teachers={teachers}
                                    rooms={rooms}
                                    timeslots={timeslots}
                                    sections={sections}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

function ManualScheduleForm({ semesters, courseOfferings, teachers, rooms, timeslots, sections }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        data: {
            semester_id: '',
            schedule: []
        }
    });

    const [newLine, setNewLine] = useState({
        course_offering_id: '',
        section_id: '',
        teacher_id: '',
        room_id: '',
        timeslot_id: ''
    });

    const selectedSemesterId = Number(data.data.semester_id || 0);
    const selectedSemester = semesters.find((semester) => Number(semester.id) === selectedSemesterId);

    const availableCourseOfferings = useMemo(() => {
        return courseOfferings.filter((offering) => !selectedSemesterId || getOfferingSemesterId(offering) === selectedSemesterId);
    }, [courseOfferings, selectedSemesterId]);

    const availableSections = useMemo(() => {
        return sections.filter((section) => {
            const matchesSemester = !selectedSemesterId || getSectionSemesterId(section) === selectedSemesterId;
            const matchesOffering = !newLine.course_offering_id || getSectionCourseOfferingId(section) === Number(newLine.course_offering_id);
            return matchesSemester && matchesOffering;
        });
    }, [sections, selectedSemesterId, newLine.course_offering_id]);

    const selectedSection = useMemo(() => {
        return sections.find((section) => Number(section.id) === Number(newLine.section_id));
    }, [sections, newLine.section_id]);

    const availableTeachers = useMemo(() => {
        if (selectedSection?.teachers && selectedSection.teachers.length > 0) {
            return selectedSection.teachers;
        }
        return teachers;
    }, [selectedSection, teachers]);

    const handleAddLine = () => {
        if (!newLine.course_offering_id || !newLine.section_id || !newLine.teacher_id || !newLine.room_id || !newLine.timeslot_id) {
            alert('Please fill all fields for the manual schedule row.');
            return;
        }

        const selectedOffering = courseOfferings.find((offering) => offering.id === Number(newLine.course_offering_id));
        const selectedSection = sections.find((section) => section.id === Number(newLine.section_id));
        const selectedTeacher = teachers.find((teacher) => teacher.id === Number(newLine.teacher_id));
        const selectedRoom = rooms.find((room) => room.id === Number(newLine.room_id));
        const selectedTimeslot = timeslots.find((timeslot) => timeslot.id === Number(newLine.timeslot_id));

        if (!selectedOffering || !selectedSection || !selectedTeacher || !selectedRoom || !selectedTimeslot) {
            alert('Invalid data. Please verify the selected values.');
            return;
        }

        const scheduleItem = {
            course_offering_id: selectedOffering.id,
            section_id: selectedSection.id,
            teacher_id: selectedTeacher.id,
            section_name: selectedSection.section_name,
            teacher_name: selectedTeacher.user?.name || selectedTeacher.full_name || 'Assigned Teacher',
            room_id: Number(newLine.room_id),
            timeslot_id: Number(newLine.timeslot_id),
            course_name: selectedOffering.course?.course_name,
            room_code: selectedRoom.room_code,
            day: selectedTimeslot.day_of_week,
            start_time: selectedTimeslot.start_time,
            end_time: selectedTimeslot.end_time
        };

        setData('data', {
            ...data.data,
            schedule: [...data.data.schedule, scheduleItem]
        });

        setNewLine({
            course_offering_id: '',
            section_id: '',
            teacher_id: '',
            room_id: '',
            timeslot_id: ''
        });
    };

    const handleRemove = (index) => {
        const newSchedule = data.data.schedule.filter((_, idx) => idx !== index);
        setData('data', { ...data.data, schedule: newSchedule });
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!data.data.semester_id) {
            alert('Please choose a semester.');
            return;
        }

        if (data.data.schedule.length === 0) {
            alert('Add at least one manual schedule entry.');
            return;
        }

        post('/schedules/generate', {
            data: data.data,
            onSuccess: () => {
                reset();
                alert('Manual schedule entries submitted.');
            }
        });
    };

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Semester *</label>
                    <select
                        value={data.data.semester_id}
                        onChange={(e) => {
                            setData('data.semester_id', e.target.value);
                            setNewLine({
                                course_offering_id: '',
                                section_id: '',
                                teacher_id: '',
                                room_id: '',
                                timeslot_id: ''
                            });
                        }}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                        required
                    >
                        <option value="">Select Semester</option>
                        {semesters?.map((semester) => (
                            <option key={semester.id} value={semester.id}>
                                {semester.name}
                            </option>
                        ))}
                    </select>
                    {errors['data.semester_id'] && (
                        <p className="mt-2 text-sm text-red-600">{errors['data.semester_id']}</p>
                    )}
                </div>

                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <p className="font-semibold text-gray-900">Manual scheduling tip</p>
                    <p className="mt-1">
                        Choose a semester first, then add the section, teacher, room, and timeslot combinations you want to override or create manually.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Course Offering</label>
                    <select
                        value={newLine.course_offering_id}
                        onChange={(e) => setNewLine({ ...newLine, course_offering_id: e.target.value, section_id: '', teacher_id: '', room_id: '', timeslot_id: '' })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                        disabled={!selectedSemesterId}
                    >
                        <option value="">Select</option>
                        {availableCourseOfferings.map((offering) => (
                            <option key={offering.id} value={offering.id}>
                                {offering.course?.course_code || 'Unnamed course'} - {offering.course?.course_name || 'Untitled'}
                            </option>
                        ))}
                    </select>
                    {!selectedSemesterId && (
                        <p className="mt-2 text-xs text-gray-500">Select a semester first to see the course offerings for that semester.</p>
                    )}
                    {selectedSemesterId && availableCourseOfferings.length === 0 && (
                        <p className="mt-2 text-xs text-yellow-700">No course offerings are available for {selectedSemester?.name ?? 'the selected semester'}.</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Section</label>
                    <select
                        value={newLine.section_id}
                        onChange={(e) => setNewLine({ ...newLine, section_id: e.target.value, teacher_id: '' })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                        disabled={!newLine.course_offering_id}
                    >
                        <option value="">Select</option>
                        {availableSections.map((section) => (
                            <option key={section.id} value={section.id}>
                                {section.section_name} (Cap: {section.capacity})
                            </option>
                        ))}
                    </select>
                    {!newLine.course_offering_id && (
                        <p className="mt-2 text-xs text-gray-500">Choose a course offering first, then pick a matching section.</p>
                    )}
                    {newLine.course_offering_id && availableSections.length === 0 && (
                        <p className="mt-2 text-xs text-yellow-700">No sections are linked to this course offering or semester.</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Teacher</label>
                    <select
                        value={newLine.teacher_id}
                        onChange={(e) => setNewLine({ ...newLine, teacher_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                        disabled={!newLine.section_id}
                    >
                        <option value="">Select</option>
                        {availableTeachers.map((teacher) => (
                            <option key={teacher.id} value={teacher.id}>
                                {teacher.user?.name || teacher.full_name || `Teacher ${teacher.id}`}
                            </option>
                        ))}
                    </select>
                    {!newLine.section_id && (
                        <p className="mt-2 text-xs text-gray-500">Select a section first to choose a teacher.</p>
                    )}
                    {newLine.section_id && availableTeachers.length === 0 && (
                        <p className="mt-2 text-xs text-yellow-700">This section has no assigned teachers yet. Choose a teacher to assign.</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Room</label>
                    <select
                        value={newLine.room_id}
                        onChange={(e) => setNewLine({ ...newLine, room_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {rooms?.map((room) => (
                            <option key={room.id} value={room.id}>
                                {room.room_code} ({room.type}, cap {room.capacity})
                            </option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Timeslot</label>
                    <select
                        value={newLine.timeslot_id}
                        onChange={(e) => setNewLine({ ...newLine, timeslot_id: e.target.value })}
                        className="mt-1 block w-full rounded-md border-gray-300"
                    >
                        <option value="">Select</option>
                        {timeslots?.map((timeslot) => (
                            <option key={timeslot.id} value={timeslot.id}>
                                {timeslot.day_of_week} {timeslot.start_time} - {timeslot.end_time}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <button
                type="button"
                onClick={handleAddLine}
                className="rounded-md bg-green-600 px-4 py-2 text-white hover:bg-green-700"
            >
                Add Manual Schedule Row
            </button>

            {data.data.schedule.length > 0 && (
                <div>
                    <h4 className="mb-2 font-semibold text-gray-900">Manual Schedule Rows</h4>
                    <div className="overflow-x-auto rounded-lg border border-gray-200">
                        <table className="min-w-full bg-white text-sm text-gray-800">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 border">Course</th>
                                    <th className="px-3 py-2 border">Section</th>
                                    <th className="px-3 py-2 border">Teacher</th>
                                    <th className="px-3 py-2 border">Room</th>
                                    <th className="px-3 py-2 border">Timeslot</th>
                                    <th className="px-3 py-2 border">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.schedule.map((item, index) => (
                                    <tr key={index}>
                                        <td className="px-3 py-2 border">{item.course_name}</td>
                                        <td className="px-3 py-2 border">{item.section_name}</td>
                                        <td className="px-3 py-2 border">{item.teacher_name || 'Not assigned'}</td>
                                        <td className="px-3 py-2 border">{item.room_code}</td>
                                        <td className="px-3 py-2 border">{item.day} {item.start_time}-{item.end_time}</td>
                                        <td className="px-3 py-2 border">
                                            <button
                                                type="button"
                                                onClick={() => handleRemove(index)}
                                                className="text-red-600 hover:text-red-800"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <button
                onClick={handleSubmit}
                disabled={processing}
                className="rounded-md bg-blue-600 px-5 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400"
            >
                {processing ? 'Submitting...' : 'Submit Manual Schedule'}
            </button>
        </div>
    );
}
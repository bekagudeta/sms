import React, { useMemo } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Edit({ schedule, sections, teachers, rooms, timeslots, otherSchedules = [] }) {
    console.log('Edit component data:', {
        sectionsCount: sections?.length || 0,
        teachersCount: teachers?.length || 0,
        roomsCount: rooms?.length || 0,
        timeslotsCount: timeslots?.length || 0,
        sections: sections?.slice(0, 2)
    });
    const { data, setData, put, processing, errors } = useForm({
        section_id: schedule.section_id,
        room_id: schedule.room_id,
        timeslot_id: schedule.timeslot_id,
        teacher_id: schedule.section?.teachers?.[0]?.id || ''
    });

    const selectedSection = useMemo(() => {
        return sections?.find((section) => Number(section.id) === Number(data.section_id));
    }, [sections, data.section_id]);

    const selectedTeacher = useMemo(() => {
        return teachers?.find((teacher) => Number(teacher.id) === Number(data.teacher_id)) ||
            selectedSection?.teachers?.find((teacher) => Number(teacher.id) === Number(data.teacher_id));
    }, [teachers, selectedSection, data.teacher_id]);

    const selectedTimeslot = useMemo(() => {
        return timeslots?.find((timeslot) => Number(timeslot.id) === Number(data.timeslot_id));
    }, [timeslots, data.timeslot_id]);

    const selectedRoom = useMemo(() => {
        return rooms?.find((room) => Number(room.id) === Number(data.room_id));
    }, [rooms, data.room_id]);

    const sectionTeachers = useMemo(() => {
        if (selectedSection?.teachers?.length) {
            return selectedSection.teachers;
        }
        return teachers || [];
    }, [selectedSection, teachers]);

    const teacherConflict = useMemo(() => {
        if (!data.teacher_id || !data.timeslot_id) return false;

        return otherSchedules.some((otherSchedule) => {
            const hasSameTimeslot = Number(otherSchedule.timeslot?.id) === Number(data.timeslot_id);
            const hasSameTeacher = otherSchedule.section?.teachers?.some(
                (teacher) => Number(teacher.id) === Number(data.teacher_id)
            );
            return hasSameTimeslot && hasSameTeacher;
        });
    }, [otherSchedules, data.teacher_id, data.timeslot_id]);

    const roomCapacityWarning = useMemo(() => {
        if (!selectedSection || !selectedRoom) return false;
        return Number(selectedRoom.capacity) < Number(selectedSection.capacity);
    }, [selectedSection, selectedRoom]);

    const sectionDetails = useMemo(() => ({
        courseCode: selectedSection?.course_offering?.course?.course_code || selectedSection?.courseOffering?.course?.course_code || 'Unknown',
        courseName: selectedSection?.course_offering?.course?.course_name || selectedSection?.courseOffering?.course?.course_name || 'Unknown',
        semesterName: selectedSection?.course_offering?.semester?.name || selectedSection?.courseOffering?.semester?.name || 'Unknown',
        sectionCapacity: selectedSection?.capacity ?? 'N/A',
        teacherCount: selectedSection?.teachers?.length ?? 0
    }), [selectedSection]);

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/schedules/${schedule.id}`);
    };

    return (
        <DashboardLayout>
            <Head title="Edit Schedule" />
            
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="mb-6">
                        <Link
                            href="/schedules"
                            className="text-indigo-600 hover:text-indigo-900"
                        >
                            &larr; Back to Schedules
                        </Link>
                    </div>

                    <h2 className="text-2xl font-bold text-gray-900 mb-6">Edit Schedule</h2>

                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <p className="text-sm font-semibold text-gray-700">Section summary</p>
                                <p className="mt-2 text-sm text-gray-600">Course: {sectionDetails.courseCode} / {sectionDetails.courseName}</p>
                                <p className="mt-1 text-sm text-gray-600">Semester: {sectionDetails.semesterName}</p>
                                <p className="mt-1 text-sm text-gray-600">Section capacity: {sectionDetails.sectionCapacity}</p>
                                <p className="mt-1 text-sm text-gray-600">Assigned teachers: {sectionDetails.teacherCount}</p>
                            </div>
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <p className="text-sm font-semibold text-gray-700">Selected room</p>
                                <p className="mt-2 text-sm text-gray-600">{selectedRoom ? `${selectedRoom.room_code} (cap ${selectedRoom.capacity})` : 'None selected'}</p>
                                {roomCapacityWarning && (
                                    <p className="mt-2 text-sm text-red-600">Warning: room capacity is smaller than section capacity.</p>
                                )}
                            </div>
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <p className="text-sm font-semibold text-gray-700">Selected teacher</p>
                                <p className="mt-2 text-sm text-gray-600">{selectedTeacher?.user?.name || selectedTeacher?.full_name || 'None selected'}</p>
                                <p className="mt-1 text-sm text-gray-600">Selected timeslot: {selectedTimeslot ? `${selectedTimeslot.day_of_week} ${selectedTimeslot.start_time}-${selectedTimeslot.end_time}` : 'None selected'}</p>
                                {teacherConflict && (
                                    <p className="mt-2 text-sm text-red-600">Warning: this teacher already has another class at the chosen timeslot.</p>
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Section
                                </label>
                                <select
                                    value={data.section_id}
                                    onChange={(e) => {
                                        const newSectionId = e.target.value;
                                        const selected = sections?.find((section) => Number(section.id) === Number(newSectionId));
                                        const defaultTeacherId = selected?.teachers?.[0]?.id || '';
                                        setData('section_id', newSectionId);
                                        setData('teacher_id', defaultTeacherId);
                                    }}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a section</option>
                                    {sections?.filter(section => section.id).map((section) => (
                                        <option key={section.id} value={section.id}>
                                            {section.course_offering?.course?.course_code || section.courseOffering?.course?.course_code || 'Section'} - {section.section_name || 'N/A'} (ID: {section.id})
                                        </option>
                                    ))}
                                </select>
                                {errors.section_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.section_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Room
                                </label>
                                <select
                                    value={data.room_id}
                                    onChange={(e) => setData('room_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a room</option>
                                    {rooms?.map((room) => (
                                        <option key={room.id} value={room.id}>
                                            {room.room_code} - Capacity: {room.capacity}
                                        </option>
                                    ))}
                                </select>
                                {errors.room_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.room_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Timeslot
                                </label>
                                <select
                                    value={data.timeslot_id}
                                    onChange={(e) => setData('timeslot_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a timeslot</option>
                                    {timeslots?.map((timeslot) => (
                                        <option key={timeslot.id} value={timeslot.id}>
                                            {timeslot.day_of_week} {timeslot.start_time} - {timeslot.end_time}
                                        </option>
                                    ))}
                                </select>
                                {errors.timeslot_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.timeslot_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Teacher
                                </label>
                                <select
                                    value={data.teacher_id}
                                    onChange={(e) => setData('teacher_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a teacher</option>
                                    {sectionTeachers?.map((teacher) => (
                                        <option key={teacher.id} value={teacher.id}>
                                            {teacher.user?.name || teacher.full_name || `Teacher ${teacher.id}`}
                                        </option>
                                    ))}
                                </select>
                                {selectedSection && selectedSection.teachers?.length === 0 && (
                                    <p className="mt-2 text-xs text-yellow-700">This section has no teacher assigned yet. Choose a teacher to attach.</p>
                                )}
                                {errors.teacher_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.teacher_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-md transition-colors duration-200"
                            >
                                {processing ? 'Updating...' : 'Update Schedule'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}

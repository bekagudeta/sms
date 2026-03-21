import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Edit({ schedule, courses, teachers, rooms, timeslots, semesters }) {
    const { data, setData, put, processing, errors } = useForm({
        course_id: schedule.course_id,
        teacher_id: schedule.teacher_id,
        room_id: schedule.room_id,
        timeslot_id: schedule.timeslot_id,
        semester_id: schedule.semester_id,
        section: schedule.section,
        max_students: schedule.max_students,
        status: schedule.status,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('schedules.update', schedule.id));
    };

    return (
        <DashboardLayout>
            <Head title="Edit Schedule" />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="mb-6">
                        <Link
                            href={route('schedules.index')}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Back to Schedules
                        </Link>
                    </div>

                    <h2 className="text-2xl font-bold text-gray-900 mb-6">Edit Schedule</h2>

                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Course
                                </label>
                                <select
                                    value={data.course_id}
                                    onChange={(e) => setData('course_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a course</option>
                                    {courses.map((course) => (
                                        <option key={course.id} value={course.id}>
                                            {course.course_code} - {course.course_name}
                                        </option>
                                    ))}
                                </select>
                                {errors.course_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.course_id}</p>
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
                                    {teachers.map((teacher) => (
                                        <option key={teacher.id} value={teacher.id}>
                                            {teacher.full_name || teacher.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.teacher_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.teacher_id}</p>
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
                                    {rooms.map((room) => (
                                        <option key={room.id} value={room.id}>
                                            {room.room_code} ({room.type}) - Capacity: {room.capacity}
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
                                    {timeslots.map((timeslot) => (
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
                                    Semester
                                </label>
                                <select
                                    value={data.semester_id}
                                    onChange={(e) => setData('semester_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a semester</option>
                                    {semesters.map((semester) => (
                                        <option key={semester.id} value={semester.id}>
                                            {semester.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.semester_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.semester_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Section
                                </label>
                                <input
                                    type="text"
                                    value={data.section}
                                    onChange={(e) => setData('section', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    maxLength="10"
                                />
                                {errors.section && (
                                    <p className="mt-1 text-sm text-red-600">{errors.section}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Max Students
                                </label>
                                <input
                                    type="number"
                                    value={data.max_students}
                                    onChange={(e) => setData('max_students', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    min="1"
                                    max="200"
                                />
                                {errors.max_students && (
                                    <p className="mt-1 text-sm text-red-600">{errors.max_students}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Status
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="scheduled">Scheduled</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="pending">Pending</option>
                                </select>
                                {errors.status && (
                                    <p className="mt-1 text-sm text-red-600">{errors.status}</p>
                                )}
                            </div>
                        </div>

                        <div className="mt-8 flex justify-end space-x-3">
                            <Link
                                href={route('schedules.show', schedule.id)}
                                className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                            >
                                Update Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}

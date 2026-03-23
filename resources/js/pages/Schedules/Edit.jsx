import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Edit({ schedule, sections, rooms, timeslots }) {
    console.log('Edit component data:', { 
        sectionsCount: sections?.length || 0, 
        roomsCount: rooms?.length || 0, 
        timeslotsCount: timeslots?.length || 0,
        sections: sections?.slice(0, 2)
    });
    const { data, setData, put, processing, errors } = useForm({
        section_id: schedule.section_id,
        room_id: schedule.room_id,
        timeslot_id: schedule.timeslot_id,
    });

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
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Section
                                </label>
                                <select
                                    value={data.section_id}
                                    onChange={(e) => setData('section_id', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">Select a section</option>
                                    {sections?.filter(section => section.id).map((section) => (
                                        <option key={section.id} value={section.id}>
                                            {section.section_name || 'N/A'} - ID: {section.id}
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

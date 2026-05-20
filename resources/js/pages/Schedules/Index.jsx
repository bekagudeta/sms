import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Link, usePage } from '@inertiajs/react';
import Pagination from '@/Components/Pagination';

export default function SchedulesIndex({ schedules, semesters, currentSemester }) {
    const { props } = usePage();
    const userRole = props.auth.user?.roles?.[0]?.name;

    const [filter, setFilter] = useState({
        day: '',
        teacher: '',
        room: ''
    });

    const schedulesData = schedules?.data ?? schedules;

    const filteredSchedules = schedulesData.filter(schedule => {
        return (
            (!filter.day || schedule.timeslot?.day_of_week === filter.day) &&
            (!filter.teacher || schedule.teacher?.user?.name?.toLowerCase().includes(filter.teacher.toLowerCase())) &&
            (!filter.room || schedule.room?.room_code.toLowerCase().includes(filter.room.toLowerCase()))
        );
    });

    return (
        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="flex justify-between items-center mb-6">
                        <h2 className="text-2xl font-bold">Schedule Management</h2>
                        <div className="space-x-3">
                            <Link
                                href="/schedules/generate"
                                className="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 inline-block"
                            >
                                Generate Schedule
                            </Link>
                            <a
                                href="/export/schedule"
                                className="bg-purple-500 text-white px-4 py-2 rounded-md hover:bg-purple-600"
                            >
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <input
                            type="text"
                            placeholder="Filter by teacher..."
                            className="px-3 py-2 border rounded-md"
                            value={filter.teacher}
                            onChange={e => setFilter({...filter, teacher: e.target.value})}
                        />
                        <input
                            type="text"
                            placeholder="Filter by room..."
                            className="px-3 py-2 border rounded-md"
                            value={filter.room}
                            onChange={e => setFilter({...filter, room: e.target.value})}
                        />
                        <select
                            className="px-3 py-2 border rounded-md"
                            value={filter.day}
                            onChange={e => setFilter({...filter, day: e.target.value})}
                        >
                            <option value="">All Days</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>

                    {/* Schedules Table */}
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {filteredSchedules.map((schedule) => (
                                    <tr key={schedule.id}>
                                        <td className="px-6 py-4">
                                            <div className="text-sm font-medium text-gray-900">
                                                {schedule.course?.course_code || schedule.section?.course_offering?.course?.course_code || 'Not assigned'}
                                            </div>
                                            <div className="text-sm text-gray-500">
                                                {schedule.course?.course_name || schedule.section?.course_offering?.course?.course_name || 'Not assigned'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {schedule.teacher_name || 'Not assigned'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {schedule.room?.room_code || 'Not assigned'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {schedule.timeslot?.day_of_week || 'Not assigned'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {schedule.timeslot?.start_time && schedule.timeslot?.end_time ? 
                                                `${schedule.timeslot.start_time} - ${schedule.timeslot.end_time}` : 
                                                'Not assigned'
                                            }
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {schedule.semester?.name || 'Not assigned'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Scheduled
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                href={`/schedules/${schedule.id}`}
                                                className="text-blue-600 hover:text-blue-900 mr-3"
                                            >
                                                View
                                            </Link>
                                            {(userRole === 'admin' || userRole === 'scheduler') && (
                                                <Link
                                                    href={`/schedules/${schedule.id}`}
                                                    method="delete"
                                                    as="button"
                                                    className="text-red-600 hover:text-red-900"
                                                    onClick={(e) => {
                                                        if (!confirm('Are you sure you want to delete this schedule?')) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        {schedules.meta && (
                            <div className="text-sm text-gray-600">
                                Showing {schedules.meta.from} to {schedules.meta.to} of {schedules.meta.total} schedules
                            </div>
                        )}
                        <Pagination links={schedules.links} />
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import ScheduleTable from '@/Components/ScheduleTable';
import { Link, usePage } from '@inertiajs/react';
import Pagination from '@/Components/Pagination';

export default function SchedulesIndex({ schedules, semesters, currentSemester }) {
    const { props } = usePage();
    const userRole = props.auth.user?.roles?.[0]?.name;
    const canExport = userRole === 'admin' || userRole === 'scheduler';

    const [filter, setFilter] = useState({
        day: '',
        teacher: '',
        room: '',
    });

    const schedulesData = schedules?.data ?? schedules;

    const filteredSchedules = schedulesData.filter((schedule) => {
        return (
            (!filter.day || schedule.timeslot?.day_of_week === filter.day) &&
            (!filter.teacher || schedule.teacher_name?.toLowerCase().includes(filter.teacher.toLowerCase())) &&
            (!filter.room || schedule.room?.room_code?.toLowerCase().includes(filter.room.toLowerCase()))
        );
    });

    const exportHref = currentSemester
        ? `/export/schedule?semester_id=${currentSemester}`
        : '/export/schedule';

    return (
        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="flex flex-wrap justify-between items-center gap-4 mb-6">
                        <div>
                            <h2 className="text-2xl font-bold">Schedule Management</h2>
                            <p className="text-sm text-gray-600 mt-1">
                                Academic year, department, year level, semester, course, instructor, classroom, and time.
                            </p>
                        </div>
                        <div className="space-x-3">
                            {(userRole === 'admin' || userRole === 'scheduler') && (
                                <Link
                                    href="/schedules/generate"
                                    className="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 inline-block"
                                >
                                    Generate Schedule
                                </Link>
                            )}
                            {canExport && (
                                <a
                                    href={exportHref}
                                    className="bg-purple-500 text-white px-4 py-2 rounded-md hover:bg-purple-600"
                                >
                                    Export to Excel
                                </a>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <input
                            type="text"
                            placeholder="Filter by instructor..."
                            className="px-3 py-2 border rounded-md"
                            value={filter.teacher}
                            onChange={(e) => setFilter({ ...filter, teacher: e.target.value })}
                        />
                        <input
                            type="text"
                            placeholder="Filter by classroom..."
                            className="px-3 py-2 border rounded-md"
                            value={filter.room}
                            onChange={(e) => setFilter({ ...filter, room: e.target.value })}
                        />
                        <select
                            className="px-3 py-2 border rounded-md"
                            value={filter.day}
                            onChange={(e) => setFilter({ ...filter, day: e.target.value })}
                        >
                            <option value="">All Days</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>

                    {filteredSchedules.length > 0 ? (
                        <ScheduleTable
                            schedules={filteredSchedules}
                            headerClass="bg-gray-50 text-gray-500"
                            actionColumn={(schedule) => (
                                <Link href={`/schedules/${schedule.id}`} className="text-blue-600 hover:text-blue-900">
                                    View
                                </Link>
                            )}
                        />
                    ) : (
                        <p className="text-gray-500 py-8 text-center">No schedules match your filters.</p>
                    )}

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

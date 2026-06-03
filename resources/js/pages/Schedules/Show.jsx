import React from 'react';
import { Head, Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function Show({ schedule }) {
    return (
        <DashboardLayout>
            <Head title="Schedule Details" />

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <div className="mb-6">
                        <Link
                            href="/schedules"
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Back to Schedules
                        </Link>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Course Information</h3>
                            <div className="space-y-3">
                                <div>
                                    <span className="font-medium text-gray-700">Course Code:</span>
                                    <span className="ml-2 text-gray-600">{schedule.course?.course_code || schedule.section?.course_offering?.course?.course_code || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Course Name:</span>
                                    <span className="ml-2 text-gray-600">{schedule.course?.course_name || schedule.section?.course_offering?.course?.course_name || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Credits:</span>
                                    <span className="ml-2 text-gray-600">{schedule.course?.credits || schedule.section?.course_offering?.course?.credits || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Hours per Week:</span>
                                    <span className="ml-2 text-gray-600">{schedule.course?.hours_per_week || schedule.section?.course_offering?.course?.hours_per_week || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Department:</span>
                                    <span className="ml-2 text-gray-600">{schedule.department_name || schedule.course?.department?.name || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Year Level:</span>
                                    <span className="ml-2 text-gray-600">{schedule.year_level || schedule.display?.year_level || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Schedule Details</h3>
                            <div className="space-y-3">
                                <div>
                                    <span className="font-medium text-gray-700">Academic Year:</span>
                                    <span className="ml-2 text-gray-600">{schedule.academic_year || schedule.display?.academic_year || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Instructor:</span>
                                    <span className="ml-2 text-gray-600">{schedule.teacher_name || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Classroom:</span>
                                    <span className="ml-2 text-gray-600">{schedule.room?.room_code || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Day:</span>
                                    <span className="ml-2 text-gray-600">{schedule.timeslot?.day_of_week || 'Not set'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Time:</span>
                                    <span className="ml-2 text-gray-600">{schedule.time_range || 'Not set'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Semester:</span>
                                    <span className="ml-2 text-gray-600">{schedule.semester?.name || schedule.section?.course_offering?.semester?.name || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Section:</span>
                                    <span className="ml-2 text-gray-600">{schedule.section?.section_name || 'Not assigned'}</span>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-700">Capacity:</span>
                                    <span className="ml-2 text-gray-600">{schedule.section?.capacity || 'Not set'}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {schedule.course?.description && (
                        <div className="mt-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Course Description</h3>
                            <p className="text-gray-600">{schedule.course.description}</p>
                        </div>
                    )}

                    <div className="mt-8 flex justify-end space-x-3">
                        <Link
                            href={`/schedules/${schedule.id}/edit`}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                        >
                            Edit Schedule
                        </Link>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

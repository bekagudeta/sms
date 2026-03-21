import React, { useState } from 'react';

import { Head, Link, router, usePage } from '@inertiajs/react';

import DashboardLayout from '@/Layouts/DashboardLayout';
import Pagination from '@/Components/Pagination';



export default function Index({ courses }) {
    const { props } = usePage();
    const filters = props.filters || {};
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');

    const coursesData = courses?.data ?? courses;



    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/courses', { search: searchTerm, page: 1 }, { preserveState: true, replace: true });
    };

    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this course?')) {
            router.delete(`/courses/${id}`);
        }
    };

    return (

        <DashboardLayout>

            <Head title="Courses" />

            

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div className="p-6 bg-white border-b border-gray-200">

                    <div className="flex justify-between items-center mb-6">

                        <h2 className="text-2xl font-bold">Courses List</h2>

                        <Link

                            href="/courses/create"

                            className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"

                        >

                            Add New Course

                        </Link>

                    </div>



                    <form onSubmit={handleSearch} className="mb-4 flex flex-col sm:flex-row sm:items-center gap-2">
                        <input
                            type="text"
                            placeholder="Search courses..."
                            className="w-full sm:flex-1 px-3 py-2 border border-gray-300 rounded-md"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        <button
                            type="submit"
                            className="w-full sm:w-auto bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition"
                        >
                            Search
                        </button>
                        {filters.search && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    router.get('/courses', { search: '', page: 1 }, { preserveState: true, replace: true });
                                }}
                                className="w-full sm:w-auto bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition"
                            >
                                Clear
                            </button>
                        )}
                    </form>



                    <div className="overflow-x-auto">

                        <table className="min-w-full divide-y divide-gray-200">

                            <thead className="bg-gray-50">

                                <tr>

                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                        Code

                                    </th>

                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                        Name

                                    </th>

                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                        Credits

                                    </th>

                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                        Teacher

                                    </th>

                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">

                                        Actions

                                    </th>

                                </tr>

                            </thead>

                            <tbody className="bg-white divide-y divide-gray-200">

                                {coursesData.map((course) => (

                                    <tr key={course.id} className="hover:bg-gray-50">

                                        <td className="px-6 py-4 whitespace-nowrap">

                                            {course.course_code}

                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">

                                            {course.course_name}

                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">

                                            {course.credits}

                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap">

                                            {course.teacher?.name || 'N/A'}

                                        </td>

                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                            <Link

                                                href={`/courses/${course.id}/edit`}

                                                className="text-indigo-600 hover:text-indigo-900 mr-3"

                                            >

                                                Edit

                                            </Link>

                                            <button

                                                onClick={() => handleDelete(course.id)}

                                                className="text-red-600 hover:text-red-900"

                                            >

                                                Delete

                                            </button>

                                        </td>

                                    </tr>

                                ))}

                            </tbody>

                        </table>

                    </div>

                    <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        {courses.meta && (
                            <div className="text-sm text-gray-600">
                                Showing {courses.meta.from} to {courses.meta.to} of {courses.meta.total} courses
                            </div>
                        )}
                        <Pagination links={courses.links} />
                    </div>

                </div>

            </div>

        </DashboardLayout>

    );

}
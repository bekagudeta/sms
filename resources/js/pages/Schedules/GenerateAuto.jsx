import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function GenerateAuto({ semesters }) {
    const { data, setData, post, processing, errors } = useForm({
        semester_id: ''
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('schedules.generate.auto'));
    };

    return (
        <DashboardLayout>
            <Head title="Generate Schedule" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h2 className="text-2xl font-bold mb-6">Generate Automatic Schedule</h2>

                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div>
                                    <label htmlFor="semester_id" className="block text-sm font-medium text-gray-700">
                                        Select Semester
                                    </label>
                                    <select
                                        id="semester_id"
                                        value={data.semester_id}
                                        onChange={(e) => setData('semester_id', e.target.value)}
                                        className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                        required
                                    >
                                        <option value="">Choose a semester</option>
                                        {semesters.map((semester) => (
                                            <option key={semester.id} value={semester.id}>
                                                {semester.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.semester_id && (
                                        <p className="mt-2 text-sm text-red-600">{errors.semester_id}</p>
                                    )}
                                </div>

                                <div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                    >
                                        {processing ? 'Generating...' : 'Generate Schedule'}
                                    </button>
                                </div>
                            </form>

                            <div className="mt-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-2">What happens when you generate a schedule?</h3>
                                <ul className="list-disc list-inside text-sm text-gray-600 space-y-1">
                                    <li>Existing schedules for the selected semester will be cleared</li>
                                    <li>The system will automatically assign teachers, rooms, and timeslots to courses</li>
                                    <li>Conflicts will be avoided based on teacher availability and room capacity</li>
                                    <li>You can manually adjust assignments after generation</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}

import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useForm } from '@inertiajs/react';

export default function StudentsCreate({ departments }) {
    const { data, setData, post, processing, errors } = useForm({
        student_id: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        department_id: '',
        level: '',
        academic_section: '',
        student_type: 'regular',
        enrollment_date: ''
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/students');
    };

    return (
        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Add New Student</h2>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Student ID
                                </label>
                                <input
                                    type="text"
                                    value={data.student_id}
                                    onChange={e => setData('student_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.student_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.student_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    First Name
                                </label>
                                <input
                                    type="text"
                                    value={data.first_name}
                                    onChange={e => setData('first_name', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.first_name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.first_name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Last Name
                                </label>
                                <input
                                    type="text"
                                    value={data.last_name}
                                    onChange={e => setData('last_name', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.last_name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.last_name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.email && (
                                    <p className="text-red-500 text-sm mt-1">{errors.email}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={e => setData('phone', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                {errors.phone && (
                                    <p className="text-red-500 text-sm mt-1">{errors.phone}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Department
                                </label>
                                <select
                                    value={data.department_id}
                                    onChange={e => setData('department_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                >
                                    <option value="">Select Department</option>
                                    {departments.map(dept => (
                                        <option key={dept.id} value={dept.id}>
                                            {dept.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.department_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.department_id}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Level
                                </label>
                                <input
                                    type="text"
                                    value={data.level}
                                    onChange={e => setData('level', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                {errors.level && (
                                    <p className="text-red-500 text-sm mt-1">{errors.level}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Academic Section (cohort)
                                </label>
                                <input
                                    type="text"
                                    value={data.academic_section}
                                    onChange={e => setData('academic_section', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.academic_section && (
                                    <p className="text-red-500 text-sm mt-1">{errors.academic_section}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Student Type
                                </label>
                                <select
                                    value={data.student_type}
                                    onChange={e => setData('student_type', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                >
                                    <option value="regular">Regular</option>
                                    <option value="weekend">Weekend</option>
                                </select>
                                {errors.student_type && (
                                    <p className="text-red-500 text-sm mt-1">{errors.student_type}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Enrollment Date
                                </label>
                                <input
                                    type="date"
                                    value={data.enrollment_date}
                                    onChange={e => setData('enrollment_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    required
                                />
                                {errors.enrollment_date && (
                                    <p className="text-red-500 text-sm mt-1">{errors.enrollment_date}</p>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end space-x-3">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50"
                            >
                                {processing ? 'Creating...' : 'Create Student'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}

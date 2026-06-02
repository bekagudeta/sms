import React, { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { useForm } from '@inertiajs/react';

export default function CreateUser({ roles, departments }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        role: '',
        department_id: '',
        level: '',
        academic_section: '',
        max_hours_per_week: ''
    });

    const handleRoleChange = (e) => {
        setData('role', e.target.value);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/users');
    };

    return (
        <DashboardLayout>
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Create User</h2>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} className="w-full px-3 py-2 border rounded-md" required />
                                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className="w-full px-3 py-2 border rounded-md" required />
                                {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} className="w-full px-3 py-2 border rounded-md" required />
                                {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                <select name="role" value={data.role} onChange={handleRoleChange} className="w-full px-3 py-2 border rounded-md" required>
                                    <option value="">Select Role</option>
                                    {roles.map(role => (
                                        <option key={role.name} value={role.name}>{role.name.charAt(0).toUpperCase() + role.name.slice(1)}</option>
                                    ))}
                                </select>
                                {errors.role && <p className="text-red-500 text-sm mt-1">{errors.role}</p>}
                            </div>
                            {/* Student fields */}
                            {data.role === 'student' && (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                        <select value={data.department_id} onChange={e => setData('department_id', e.target.value)} className="w-full px-3 py-2 border rounded-md" required>
                                            <option value="">Select Department</option>
                                            {departments.map(dept => (
                                                <option key={dept.id} value={dept.id}>{dept.name}</option>
                                            ))}
                                        </select>
                                        {errors.department_id && <p className="text-red-500 text-sm mt-1">{errors.department_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Level</label>
                                        <input type="text" value={data.level} onChange={e => setData('level', e.target.value)} className="w-full px-3 py-2 border rounded-md" required />
                                        {errors.level && <p className="text-red-500 text-sm mt-1">{errors.level}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Academic Section (cohort)</label>
                                        <input type="text" value={data.academic_section} onChange={e => setData('academic_section', e.target.value)} className="w-full px-3 py-2 border rounded-md" required placeholder="e.g. SE-3A" />
                                        {errors.academic_section && <p className="text-red-500 text-sm mt-1">{errors.academic_section}</p>}
                                    </div>
                                </>
                            )}
                            {/* Teacher fields */}
                            {data.role === 'teacher' && (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                        <select value={data.department_id} onChange={e => setData('department_id', e.target.value)} className="w-full px-3 py-2 border rounded-md" required>
                                            <option value="">Select Department</option>
                                            {departments.map(dept => (
                                                <option key={dept.id} value={dept.id}>{dept.name}</option>
                                            ))}
                                        </select>
                                        {errors.department_id && <p className="text-red-500 text-sm mt-1">{errors.department_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Max Hours Per Week</label>
                                        <input type="number" min="1" max="38" value={data.max_hours_per_week} onChange={e => setData('max_hours_per_week', e.target.value)} className="w-full px-3 py-2 border rounded-md" required />
                                        {errors.max_hours_per_week && <p className="text-red-500 text-sm mt-1">{errors.max_hours_per_week}</p>}
                                    </div>
                                </>
                            )}
                        </div>
                        <button type="submit" className="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700" disabled={processing}>
                            {processing ? 'Creating...' : 'Create User'}
                        </button>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}

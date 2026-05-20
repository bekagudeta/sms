import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Form from '@/Components/Form';

export default function Edit({ teacher, departments }) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: teacher.first_name || '',
        last_name: teacher.last_name || '',
        email: teacher.email || '',
        phone: teacher.phone || '',
        department_id: teacher.department_id || '',
        qualification: teacher.qualification || '',
        max_hours_per_week: teacher.max_hours_per_week || 20
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/teachers/${teacher.id}`);
    };

    const fields = [
        { name: 'first_name', label: 'First Name', type: 'text', required: true },
        { name: 'last_name', label: 'Last Name', type: 'text', required: true },
        { name: 'email', label: 'Email', type: 'email', required: true },
        { name: 'phone', label: 'Phone', type: 'text' },
        { 
            name: 'department_id', 
            label: 'Department', 
            type: 'select', 
            required: true,
            options: departments?.map(dept => ({
                value: dept.id,
                label: dept.name
            })) || []
        },
        { name: 'qualification', label: 'Qualification', type: 'text' },
        { name: 'max_hours_per_week', label: 'Max Hours Per Week', type: 'number', required: true }
    ];

    return (
        <DashboardLayout>
            <Head title="Edit Teacher" />
            
            <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
                <div className="p-6 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                    <h2 className="text-2xl font-bold">Edit Teacher</h2>
                    <p className="text-blue-100">Update teacher information</p>
                </div>
                
                <div className="p-6">
                    <Form
                        fields={fields}
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={handleSubmit}
                        processing={processing}
                        submitText="Update Teacher"
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}

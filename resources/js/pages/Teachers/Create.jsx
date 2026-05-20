import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import Form from '@/Components/Form';

export default function Create({ departments }) {
    const { data, setData, post, processing, errors } = useForm({
        teacher_id: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        department_id: '',
        qualification: '',
        max_hours_per_week: 20
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/teachers');
    };

    const fields = [
        { name: 'teacher_id', label: 'Teacher ID', type: 'text', required: true },
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
        <AuthLayout>
            <Head title="Create Teacher" />
            
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Add New Teacher</h2>
                    
                    <Form
                        fields={fields}
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={handleSubmit}
                        processing={processing}
                        submitText="Create Teacher"
                    />
                </div>
            </div>
        </AuthLayout>
    );
}

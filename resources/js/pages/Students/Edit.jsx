import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import Form from '@/Components/Form';

export default function Edit({ student }) {
    const { data, setData, put, processing, errors } = useForm({
        name: student.name || '',
        email: student.email || '',
        age: student.age || '',
        date_of_birth: student.date_of_birth || '',
        user_id: student.user_id || ''
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/students/${student.id}`);
    };

    const fields = [
        { name: 'name', label: 'Name', type: 'text', required: true },
        { name: 'email', label: 'Email', type: 'email', required: true },
        { name: 'age', label: 'Age', type: 'number', required: true },
        { name: 'date_of_birth', label: 'Date of Birth', type: 'date' },
        { name: 'user_id', label: 'User ID', type: 'number', required: true }
    ];

    return (
        <AuthLayout>
            <Head title="Edit Student" />
            
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Edit Student</h2>
                    
                    <Form
                        fields={fields}
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={handleSubmit}
                        processing={processing}
                        submitText="Update Student"
                    />
                </div>
            </div>
        </AuthLayout>
    );
}
import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import Form from '@/Components/Form';

export default function Create({ teachers, departments, semesters }) {
    const { data, setData, post, processing, errors } = useForm({
        course_code: '',
        course_name: '',
        description: '',
        credits: '',
        hours_per_week: '',
        department_id: '',
        level: 'undergraduate'
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/courses');
    };

    const fields = [
        { name: 'course_code', label: 'Course Code', type: 'text', required: true },
        { name: 'course_name', label: 'Course Name', type: 'text', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'credits', label: 'Credits', type: 'number', required: true },
        { name: 'hours_per_week', label: 'Hours Per Week', type: 'number', required: true },
        { 
            name: 'department_id', 
            label: 'Department', 
            type: 'select',
            options: departments.map(d => ({ value: d.id, label: d.name })),
            required: true 
        },
        { 
            name: 'level', 
            label: 'Level', 
            type: 'select',
            options: [
                { value: 'undergraduate', label: 'Undergraduate' },
                { value: 'graduate', label: 'Graduate' },
                { value: 'diploma', label: 'Diploma' }
            ],
            required: true 
        }
    ];

    return (
        <AuthLayout>
            <Head title="Create Course" />
            
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 bg-white border-b border-gray-200">
                    <h2 className="text-2xl font-bold mb-6">Add New Course</h2>
                    
                    <Form
                        fields={fields}
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={handleSubmit}
                        processing={processing}
                        submitText="Create Course"
                    />
                </div>
            </div>
        </AuthLayout>
    );
}

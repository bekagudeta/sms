import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Form from '@/Components/Form';

export default function Edit({ course, teachers, departments, semesters }) {
    const { data, setData, put, processing, errors } = useForm({
        course_code: course.course_code || '',
        course_name: course.course_name || '',
        description: course.description || '',
        credits: course.credits || '',
        hours_per_week: course.hours_per_week || '',
        department_id: course.department_id || '',
        level: course.level || 'undergraduate'
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/courses/${course.id}`);
    };

    const fields = [
        { name: 'course_code', label: 'Course Code', type: 'text', required: true },
        { name: 'course_name', label: 'Course Name', type: 'text', required: true },
        { name: 'description', label: 'Description', type: 'textarea' },
        { name: 'credits', label: 'Credits', type: 'number', required: true, min: 1, max: 38 },
        { name: 'hours_per_week', label: 'Hours Per Week', type: 'number', required: true, min: 1, max: 38 },
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
        <DashboardLayout>
            <Head title="Edit Course" />
            
            <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
                <div className="p-6 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                    <h2 className="text-2xl font-bold">Edit Course</h2>
                    <p className="text-blue-100">Update course information</p>
                </div>
                
                <div className="p-6">
                    <Form
                        fields={fields}
                        data={data}
                        setData={setData}
                        errors={errors}
                        onSubmit={handleSubmit}
                        processing={processing}
                        submitText="Update Course"
                    />
                </div>
            </div>
        </DashboardLayout>
    );
}

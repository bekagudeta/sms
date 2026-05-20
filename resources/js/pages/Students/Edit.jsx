import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Form from '@/Components/Form';

export default function Edit({ student, departments }) {
    const { data, setData, put, processing, errors } = useForm({
        student_id: student.student_id || '',
        first_name: student.first_name || '',
        last_name: student.last_name || '',
        email: student.email || '',
        phone: student.phone || '',
        department_id: student.department_id || '',
        level: student.level || '',
        section: student.section || '',
        grade: student.grade || '',
        status: student.status || '',
        enrollment_date: student.enrollment_date ? student.enrollment_date.split(' ')[0] : ''
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/students/${student.id}`);
    };

    const fields = [
        { name: 'student_id', label: 'Student ID', type: 'text', required: true },
        { name: 'first_name', label: 'First Name', type: 'text', required: true },
        { name: 'last_name', label: 'Last Name', type: 'text', required: true },
        { name: 'email', label: 'Email', type: 'email', required: true },
        { name: 'phone', label: 'Phone', type: 'text' },
        { name: 'department_id', label: 'Department', type: 'select', required: false, options: departments.map(dept => ({ value: dept.id, label: dept.name })) },
        { name: 'level', label: 'Level', type: 'text' },
        { name: 'section', label: 'Section', type: 'text' },
        { name: 'grade', label: 'Grade', type: 'number', min: 1, max: 12 },
        { name: 'status', label: 'Status', type: 'select', options: [
            { value: 'active', label: 'Active' },
            { value: 'inactive', label: 'Inactive' },
            { value: 'pending', label: 'Pending' },
            { value: 'graduated', label: 'Graduated' },
            { value: 'suspended', label: 'Suspended' }
        ] },
        { name: 'enrollment_date', label: 'Enrollment Date', type: 'date' }
    ];

    return (
        <DashboardLayout>
            <Head title="Edit Student" />
            
            <div className="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-200">
                <div className="p-6 bg-gradient-to-r from-blue-600 to-purple-700 text-white">
                    <h2 className="text-2xl font-bold">Edit Student</h2>
                    <p className="text-blue-100">Update student information</p>
                </div>
                
                <div className="p-6">
                    <div className="max-w-3xl mx-auto">
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
            </div>
        </DashboardLayout>
    );
}

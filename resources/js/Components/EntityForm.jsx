import React from 'react';

export default function EntityForm({ isOpen, onClose, config, formData, setFormData, errors, processing, editingItem, relatedOptions = {}, onSubmit }) {
    if (!isOpen) return null;

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(name, type === 'checkbox' ? checked : value);
    };

    const renderField = (fieldName, fieldConfig = {}) => {
        const value = formData[fieldName];
        const error = errors[fieldName];
        const isRequired = config.requiredColumns.includes(fieldName);
        const isOptional = config.optionalColumns.includes(fieldName);

        const fieldType = fieldConfig.type || 'text';
        const label = fieldConfig.label || fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

        switch (fieldType) {
            case 'select':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <select
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        >
                            <option value="">Select {label}</option>
                            {fieldConfig.options?.map(option => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            case 'textarea':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <textarea
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            rows={3}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        />
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            case 'number':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <input
                            type="number"
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            min={fieldConfig.min}
                            max={fieldConfig.max}
                            step={fieldConfig.step}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        />
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            case 'date':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <input
                            type="date"
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        />
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            case 'time':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <input
                            type="time"
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        />
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            case 'checkbox':
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="flex items-center">
                            <input
                                type="checkbox"
                                name={fieldName}
                                checked={value}
                                onChange={handleChange}
                                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <span className="ml-2 text-sm text-gray-700">{label}</span>
                        </label>
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );

            default:
                return (
                    <div key={fieldName} className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">
                            {label} {isRequired && <span className="text-red-500">*</span>}
                        </label>
                        <input
                            type={fieldType}
                            name={fieldName}
                            value={value}
                            onChange={handleChange}
                            placeholder={fieldConfig.placeholder}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 ${
                                error ? 'border-red-300' : 'border-gray-300'
                            }`}
                        />
                        {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
                    </div>
                );
        }
    };

    const getFieldConfig = (fieldName) => {
        // Entity-specific field configurations
        const configs = {
            students: {
                email: { type: 'email', label: 'Email Address' },
                academic_section: { type: 'text', label: 'Academic Section (cohort, e.g. SE-3A)' },
                student_type: {
                    type: 'select',
                    label: 'Student Type',
                    options: [
                        { value: 'regular', label: 'Regular' },
                        { value: 'weekend', label: 'Weekend' }
                    ]
                },
                level: {
                    type: 'select',
                    options: relatedOptions.levels || [
                        { value: 'bachelor', label: 'Bachelor' },
                        { value: 'master', label: 'Master' },
                        { value: 'phd', label: 'PhD' },
                        { value: 'diploma', label: 'Diploma' },
                        { value: 'certificate', label: 'Certificate' }
                    ]
                },
                department_id: {
                    type: 'select',
                    options: (relatedOptions.departments || []).map(dept => ({ value: dept.id, label: dept.name }))
                },
                grade: { type: 'number', min: 1, max: 12, label: 'Grade Level' },
                status: { 
                    type: 'select', 
                    options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ]
                }
            },
            teachers: {
                email: { type: 'email', label: 'Email Address' },
                department_id: {
                    type: 'select',
                    options: (relatedOptions.departments || []).map(dept => ({ value: dept.id, label: dept.name }))
                },
                qualification: {
                    type: 'select',
                    options: relatedOptions.qualifications || [
                        { value: 'BSc', label: 'BSc' },
                        { value: 'MSc', label: 'MSc' },
                        { value: 'PhD', label: 'PhD' },
                        { value: 'MBA', label: 'MBA' },
                        { value: 'MA', label: 'MA' },
                        { value: 'EdD', label: 'EdD' },
                        { value: 'Other', label: 'Other' }
                    ]
                },
                max_hours_per_week: { type: 'number', min: 1, max: 38, label: 'Max Hours per Week' },
                specialization: { type: 'text', label: 'Specialization' },
                phone: { type: 'text', label: 'Phone' },
            },
            courses: {
                course_code: { label: 'Course Code' },
                course_name: { label: 'Course Name' },
                credits: { type: 'number', min: 1, max: 38, label: 'Credits' },
                hours_per_week: { type: 'number', min: 1, max: 38, label: 'Hours per Week' },
                department_id: {
                    type: 'select',
                    label: 'Department',
                    options: (relatedOptions.departments || []).map(dept => ({ value: dept.id, label: dept.name }))
                },
                description: { type: 'textarea', label: 'Description' },
                level: { 
                    type: 'select',
                    options: relatedOptions.levels || [
                        { value: 'undergraduate', label: 'Undergraduate' },
                        { value: 'graduate', label: 'Graduate' },
                        { value: 'certificate', label: 'Certificate' },
                        { value: 'professional', label: 'Professional' }
                    ]
                },
                required_room_type: {
                    type: 'select',
                    label: 'Required Room Type',
                    options: relatedOptions.roomTypes || [
                        { value: 'lecture', label: 'Lecture' },
                        { value: 'lab', label: 'Laboratory' },
                        { value: 'seminar', label: 'Seminar' },
                        { value: 'conference', label: 'Conference' },
                        { value: 'studio', label: 'Studio' }
                    ]
                }
            },
            semesters: {
                academic_year: {
                    type: 'text',
                    label: 'Academic Year',
                    placeholder: 'e.g. 2024-2025 or 2024',
                },
                start_date: { type: 'date', label: 'Start Date' },
                end_date: { type: 'date', label: 'End Date' },
                is_active: { type: 'checkbox', label: 'Is Active' },
            },
            'course-offerings': {
                course_id: {
                    type: 'select',
                    label: 'Course',
                    options: (relatedOptions.courses || []).map(course => ({ value: course.id, label: course.course_code }))
                },
                semester_id: {
                    type: 'select',
                    label: 'Semester',
                    options: (relatedOptions.semesters || []).map((semester) => ({
                        value: semester.id,
                        label: semester.label || semester.name,
                    })),
                },
                expected_students: { type: 'number', min: 0, label: 'Expected Students' }
            },
            sections: {
                course_offering_id: {
                    type: 'select',
                    label: 'Course Offering',
                    options: (relatedOptions.courseOfferings || []).map(offering => ({ value: offering.id, label: offering.label }))
                },
                section_name: { label: 'Section Name' },
                capacity: { type: 'number', min: 1, label: 'Capacity' }
            },
            enrollments: {
                student_id: {
                    type: 'select',
                    label: 'Student',
                    options: (relatedOptions.students || []).map(student => ({ value: student.id, label: student.label }))
                },
                section_id: {
                    type: 'select',
                    label: 'Course Section',
                    options: (relatedOptions.sections || []).map(section => ({ value: section.id, label: section.label }))
                },
                enrolled_at: { type: 'date', label: 'Enrolled At' },
                student_code_value: { label: 'Student Code Value' }
            },
            'section-teachers': {
                section_id: {
                    type: 'select',
                    label: 'Section',
                    options: (relatedOptions.sections || []).map(section => ({ value: section.id, label: section.label }))
                },
                teacher_id: {
                    type: 'select',
                    label: 'Teacher',
                    options: (relatedOptions.teachers || []).map(teacher => ({ value: teacher.id, label: teacher.label }))
                },
            },
            rooms: {
                room_code: { label: 'Room Code' },
                building: { label: 'Building' },
                floor: { type: 'number', min: 0, label: 'Floor' },
                capacity: { type: 'number', min: 1, label: 'Capacity' },
                type: {
                    type: 'select',
                    options: [
                        { value: 'lecture', label: 'Lecture' },
                        { value: 'lab', label: 'Laboratory' },
                        { value: 'seminar', label: 'Seminar' },
                        { value: 'conference', label: 'Conference' }
                    ]
                },
                has_projector: { type: 'checkbox', label: 'Has Projector' },
                has_computers: { type: 'checkbox', label: 'Has Computers' },
                computer_count: { type: 'number', min: 0, label: 'Computer Count' }
            },
            timeslots: {
                day_of_week: { 
                    type: 'select',
                    options: [
                        { value: 'Monday', label: 'Monday' },
                        { value: 'Tuesday', label: 'Tuesday' },
                        { value: 'Wednesday', label: 'Wednesday' },
                        { value: 'Thursday', label: 'Thursday' },
                        { value: 'Friday', label: 'Friday' },
                        { value: 'Saturday', label: 'Saturday' },
                        { value: 'Sunday', label: 'Sunday' }
                    ]
                },
                start_time: { type: 'time', label: 'Start Time' },
                end_time: { type: 'time', label: 'End Time' }
            }
        };

        return configs[config.routePrefix]?.[fieldName] || {};
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75" onClick={onClose}></div>
                
                <div className="relative bg-white rounded-lg max-w-2xl w-full p-6 max-h-screen overflow-y-auto">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-semibold text-gray-900">
                            {editingItem ? `Edit ${config.singular}` : `Add ${config.singular}`}
                        </h2>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-500"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={onSubmit}>
                        {/* Render required fields first */}
                        {config.requiredColumns.map(fieldName => 
                            renderField(fieldName, getFieldConfig(fieldName))
                        )}

                        {/* Render optional fields */}
                        {config.optionalColumns.map(fieldName => 
                            renderField(fieldName, getFieldConfig(fieldName))
                        )}

                        <div className="flex justify-end space-x-3 mt-6">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400"
                            >
                                {processing ? 'Saving...' : (editingItem ? 'Update' : 'Create')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

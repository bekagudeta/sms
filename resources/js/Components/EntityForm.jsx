import React, { useMemo, useState } from 'react';

/**
 * Add / Edit form for entities.
 *
 * Each entity config provides `formFields` — an explicit list of form fields
 * decoupled from the (Excel) import columns, mirroring the server validation
 * rules. These get real client-side validation. If an entity is ever missing
 * `formFields`, a minimal text-input fallback keeps the form from crashing.
 */
export default function EntityForm({
    isOpen,
    onClose,
    config,
    formData,
    setFormData,
    errors = {},
    processing,
    editingItem,
    relatedOptions = {},
    onSubmit,
}) {
    const [clientErrors, setClientErrors] = useState({});

    // Every entity declares `formFields` (in config/entities.js). The fallback
    // below only guards against a misconfigured/new entity: it renders plain
    // text inputs derived from the import columns so the form never crashes.
    const fields = useMemo(() => {
        if (config.formFields?.length) {
            return config.formFields;
        }
        const required = (config.requiredColumns || []).map((name) => ({ name, required: true, type: 'text' }));
        const optional = (config.optionalColumns || []).map((name) => ({ name, required: false, type: 'text' }));
        return [...required, ...optional];
    }, [config]);

    if (!isOpen) return null;

    // A field is required either always, or only when creating (requiredOnCreate).
    const isFieldRequired = (field) => field.required || (field.requiredOnCreate && !editingItem);

    // Normalise any related-option list into [{ value, label }].
    const resolveOptions = (field) => {
        const key = field.optionsKey;
        const related = key && Array.isArray(relatedOptions[key]) ? relatedOptions[key] : null;
        const source = related && related.length ? related : field.options || [];

        return source.map((option) => {
            if (option && option.value !== undefined && option.label !== undefined) {
                return option; // already in the right shape
            }
            const value = option[field.valueField || 'id'] ?? option.value ?? option.id;
            const label =
                option[field.labelField] ??
                option.label ??
                option.name ??
                option.course_code ??
                String(value);
            return { value, label };
        });
    };

    const validateField = (field, raw) => {
        const value = raw === null || raw === undefined ? '' : String(raw).trim();
        const name = field.label || field.name;

        if (isFieldRequired(field) && value === '') {
            return `${name} is required.`;
        }
        if (value === '') return null; // optional + empty is fine

        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'Enter a valid email address.';
        }
        if (field.minLength && value.length < field.minLength) {
            return `${name} must be at least ${field.minLength} characters.`;
        }
        if (field.maxLength && value.length > field.maxLength) {
            return `${name} must be at most ${field.maxLength} characters.`;
        }
        if (field.type === 'number') {
            const n = Number(value);
            if (Number.isNaN(n)) return `${name} must be a number.`;
            if (field.integer && !Number.isInteger(n)) return `${name} must be a whole number.`;
            if (field.min !== undefined && field.min !== null && n < field.min) return `${name} must be at least ${field.min}.`;
            if (field.max !== undefined && field.max !== null && n > field.max) return `${name} must be at most ${field.max}.`;
        }
        if (field.pattern && !new RegExp(field.pattern).test(value)) {
            return field.patternMessage || `${name} is not valid.`;
        }
        return null;
    };

    const handleChange = (e) => {
        const { name, value, type, checked } = e.target;
        setFormData(name, type === 'checkbox' ? checked : value);
        // Clear the client error for this field as the user corrects it.
        setClientErrors((prev) => {
            if (!prev[name]) return prev;
            const next = { ...prev };
            delete next[name];
            return next;
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const found = {};
        fields.forEach((field) => {
            const message = validateField(field, formData[field.name]);
            if (message) found[field.name] = message;
        });

        if (Object.keys(found).length) {
            setClientErrors(found);
            // Focus the first invalid field for accessibility.
            const firstName = fields.find((f) => found[f.name])?.name;
            if (firstName) {
                const el = document.querySelector(`[name="${firstName}"]`);
                el?.focus();
            }
            return;
        }

        setClientErrors({});
        onSubmit(e);
    };

    const renderField = (field) => {
        const value = formData[field.name] ?? '';
        const error = clientErrors[field.name] || errors[field.name];
        const fieldType = field.type || 'text';
        const required = isFieldRequired(field);
        const label = field.label || field.name.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
        const baseClasses = `app-input mt-1 block w-full ${error ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''}`;

        const labelEl = (
            <label htmlFor={field.name} className="block text-sm font-medium text-primary">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
        );
        const errorEl = error && <p className="mt-1 text-sm text-red-600">{error}</p>;
        const helpEl = field.helpOnEdit && editingItem && !error && (
            <p className="mt-1 text-xs text-gray-500">{field.helpOnEdit}</p>
        );

        if (fieldType === 'select') {
            return (
                <div key={field.name} className="mb-4">
                    {labelEl}
                    <select id={field.name} name={field.name} value={value} onChange={handleChange} className={baseClasses}>
                        <option value="">Select {label}…</option>
                        {resolveOptions(field).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {errorEl}
                </div>
            );
        }

        if (fieldType === 'textarea') {
            return (
                <div key={field.name} className="mb-4">
                    {labelEl}
                    <textarea id={field.name} name={field.name} value={value} onChange={handleChange} rows={3} className={baseClasses} />
                    {errorEl}
                </div>
            );
        }

        if (fieldType === 'checkbox') {
            return (
                <div key={field.name} className="mb-4">
                    <label className="flex items-center">
                        <input
                            type="checkbox"
                            id={field.name}
                            name={field.name}
                            checked={!!formData[field.name]}
                            onChange={handleChange}
                            className="h-4 w-4 rounded border-primary/30 text-primary focus:ring-primary"
                        />
                        <span className="ml-2 text-sm text-primary">{label}</span>
                    </label>
                    {errorEl}
                </div>
            );
        }

        return (
            <div key={field.name} className="mb-4">
                {labelEl}
                <input
                    type={fieldType}
                    id={field.name}
                    name={field.name}
                    value={value}
                    onChange={handleChange}
                    placeholder={field.placeholder}
                    required={required}
                    maxLength={field.maxLength}
                    min={field.min}
                    max={field.max}
                    step={field.step}
                    inputMode={fieldType === 'number' ? 'numeric' : undefined}
                    className={baseClasses}
                    autoComplete={fieldType === 'password' ? 'new-password' : undefined}
                />
                {errorEl}
                {helpEl}
            </div>
        );
    };

    const hasServerErrors = Object.keys(errors).length > 0;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-screen items-center justify-center px-4 py-6">
                <div className="fixed inset-0 bg-black/40" onClick={onClose}></div>

                <div className="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                    <div className="mb-6 flex items-center justify-between">
                        <h2 className="text-xl font-semibold text-primary">
                            {editingItem ? `Edit ${config.singular}` : `Add ${config.singular}`}
                        </h2>
                        <button onClick={onClose} className="text-gray-400 transition hover:text-gray-600" aria-label="Close">
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {hasServerErrors && (
                        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            Please correct the highlighted fields and try again.
                        </div>
                    )}

                    <form onSubmit={handleSubmit} noValidate>
                        {fields.map(renderField)}

                        <div className="mt-6 flex justify-end space-x-3">
                            <button type="button" onClick={onClose} className="app-secondary-btn">
                                Cancel
                            </button>
                            <button type="submit" disabled={processing} className="app-primary-btn">
                                {processing ? 'Saving…' : editingItem ? 'Update' : 'Create'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

/**
 * Legacy per-entity field descriptors for entities that don't yet declare
 * `formFields`. Kept so courses/rooms/semesters/etc. continue to work unchanged.
 */
function legacyFieldConfig(config, fieldName, relatedOptions) {
    const configs = {
        courses: {
            course_code: { label: 'Course Code' },
            course_name: { label: 'Course Name' },
            credits: { type: 'number', min: 1, max: 38, label: 'Credits' },
            hours_per_week: { type: 'number', min: 1, max: 38, label: 'Hours per Week' },
            department_id: {
                type: 'select',
                label: 'Department',
                options: (relatedOptions.departments || []).map((dept) => ({ value: dept.id, label: dept.name })),
            },
            description: { type: 'textarea', label: 'Description' },
            level: {
                type: 'select',
                options: relatedOptions.levels || [
                    { value: 'undergraduate', label: 'Undergraduate' },
                    { value: 'graduate', label: 'Graduate' },
                    { value: 'certificate', label: 'Certificate' },
                    { value: 'professional', label: 'Professional' },
                ],
            },
            required_room_type: {
                type: 'select',
                label: 'Required Room Type',
                options: relatedOptions.roomTypes || [
                    { value: 'lecture', label: 'Lecture' },
                    { value: 'lab', label: 'Laboratory' },
                    { value: 'seminar', label: 'Seminar' },
                    { value: 'conference', label: 'Conference' },
                    { value: 'studio', label: 'Studio' },
                ],
            },
        },
        semesters: {
            academic_year: { type: 'text', label: 'Academic Year', placeholder: 'e.g. 2024-2025 or 2024' },
            start_date: { type: 'date', label: 'Start Date' },
            end_date: { type: 'date', label: 'End Date' },
            is_active: { type: 'checkbox', label: 'Is Active' },
        },
        'course-offerings': {
            course_id: {
                type: 'select',
                label: 'Course',
                options: (relatedOptions.courses || []).map((course) => ({ value: course.id, label: course.course_code })),
            },
            semester_id: {
                type: 'select',
                label: 'Semester',
                options: (relatedOptions.semesters || []).map((semester) => ({ value: semester.id, label: semester.label || semester.name })),
            },
            expected_students: { type: 'number', min: 0, label: 'Expected Students' },
        },
        sections: {
            course_offering_id: {
                type: 'select',
                label: 'Course Offering',
                options: (relatedOptions.courseOfferings || []).map((offering) => ({ value: offering.id, label: offering.label })),
            },
            section_name: { label: 'Section Name' },
            capacity: { type: 'number', min: 1, label: 'Capacity' },
        },
        enrollments: {
            student_id: {
                type: 'select',
                label: 'Student',
                options: (relatedOptions.students || []).map((student) => ({ value: student.id, label: student.label })),
            },
            section_id: {
                type: 'select',
                label: 'Course Section',
                options: (relatedOptions.sections || []).map((section) => ({ value: section.id, label: section.label })),
            },
            enrolled_at: { type: 'date', label: 'Enrolled At' },
            student_code_value: { label: 'Student Code Value' },
        },
        'section-teachers': {
            section_id: {
                type: 'select',
                label: 'Section',
                options: (relatedOptions.sections || []).map((section) => ({ value: section.id, label: section.label })),
            },
            teacher_id: {
                type: 'select',
                label: 'Teacher',
                options: (relatedOptions.teachers || []).map((teacher) => ({ value: teacher.id, label: teacher.label })),
            },
        },
        schedulers: {
            email: { type: 'email', label: 'Email Address' },
            password: { type: 'password', label: 'Password' },
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
                    { value: 'conference', label: 'Conference' },
                ],
            },
            has_projector: { type: 'checkbox', label: 'Has Projector' },
            has_computers: { type: 'checkbox', label: 'Has Computers' },
            computer_count: { type: 'number', min: 0, label: 'Computer Count' },
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
                    { value: 'Sunday', label: 'Sunday' },
                ],
            },
            start_time: { type: 'time', label: 'Start Time' },
            end_time: { type: 'time', label: 'End Time' },
        },
        departments: {
            code: { label: 'Code' },
            name: { label: 'Name' },
            description: { type: 'textarea', label: 'Description' },
        },
    };

    return configs[config.routePrefix]?.[fieldName] || {};
}

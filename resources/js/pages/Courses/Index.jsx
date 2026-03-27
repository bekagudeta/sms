import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function CoursesIndex({ courses, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="courses"
                initialData={courses?.data || courses}
                permissions={permissions || {
                    view: true,
                    create: true,
                    edit: true,
                    delete: true,
                    import: true
                }}
            />
        </DashboardLayout>
    );
}

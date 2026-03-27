import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function EnrollmentsIndex({ enrollments, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="enrollments"
                initialData={enrollments?.data || enrollments}
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

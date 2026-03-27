import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function CourseOfferingsIndex({ courseofferings, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="course-offerings"
                initialData={courseofferings?.data || courseofferings}
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

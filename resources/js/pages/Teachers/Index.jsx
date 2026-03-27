import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function TeachersIndex({ teachers, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="teachers"
                initialData={teachers?.data || teachers}
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

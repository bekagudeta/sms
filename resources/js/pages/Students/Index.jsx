import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/Components/EntityManager';

export default function StudentsIndex({ students, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="students"
                initialData={students?.data || students}
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

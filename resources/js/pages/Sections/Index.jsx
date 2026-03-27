import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function SectionsIndex({ sections, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="sections"
                initialData={sections?.data || sections}
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

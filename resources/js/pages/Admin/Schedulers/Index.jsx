import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/Components/EntityManager';

export default function AdminSchedulersIndex({ schedulers, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="schedulers"
                initialData={schedulers?.data || schedulers}
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

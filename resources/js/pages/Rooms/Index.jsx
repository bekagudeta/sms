import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/Components/EntityManager';

export default function RoomsIndex({ rooms, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="rooms"
                initialData={rooms?.data || rooms}
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

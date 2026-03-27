import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function TimeslotsIndex({ timeslots, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="timeslots"
                initialData={timeslots?.data || timeslots}
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

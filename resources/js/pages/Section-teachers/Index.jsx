import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';

export default function SectionTeachersIndex({ sectionteachers, permissions }) {
    return (
        <DashboardLayout>
            <EntityManager
                entityType="section-teachers"
                initialData={sectionteachers?.data || sectionteachers}
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

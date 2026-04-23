import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import EntityManager from '@/components/EntityManager';
import { getEntityConfig } from '@/config/entities';

export default function EntityIndex({ entityType, data, filters, permissions, relatedOptions }) {
    const config = getEntityConfig(entityType);

    return (
        <DashboardLayout>
            <EntityManager
                entityType={entityType}
                initialData={data}
                filters={filters}
                permissions={permissions}
                relatedOptions={relatedOptions}
            />
        </DashboardLayout>
    );
}

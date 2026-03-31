import React, { useState, useMemo } from 'react';

const ChevronUpIcon = () => (
    <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
    </svg>
);

const ChevronDownIcon = () => (
    <svg className="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
    </svg>
);

export default function DataTable({ columns, data, onEdit, onDelete, onSelect, loading = false }) {
    const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });
    const [selectedRows, setSelectedRows] = useState(new Set());

    const sortedData = useMemo(() => {
        if (!sortConfig.key) return data;

        return [...data].sort((a, b) => {
            const aValue = getNestedValue(a, sortConfig.key);
            const bValue = getNestedValue(b, sortConfig.key);

            if (aValue === null || aValue === undefined) return 1;
            if (bValue === null || bValue === undefined) return -1;

            if (aValue < bValue) return sortConfig.direction === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });
    }, [data, sortConfig]);

    const handleSort = (key) => {
        let direction = 'asc';
        if (sortConfig.key === key && sortConfig.direction === 'asc') {
            direction = 'desc';
        }
        setSortConfig({ key, direction });
    };

    const handleSelectAll = (checked) => {
        if (checked) {
            const allIds = new Set(data.map(item => item.id));
            setSelectedRows(allIds);
            onSelect?.(Array.from(allIds));
        } else {
            setSelectedRows(new Set());
            onSelect?.([]);
        }
    };

    const handleSelectRow = (id, checked) => {
        const newSelected = new Set(selectedRows);
        if (checked) {
            newSelected.add(id);
        } else {
            newSelected.delete(id);
        }
        setSelectedRows(newSelected);
        onSelect?.(Array.from(newSelected));
    };

    const getNestedValue = (obj, path) => {
        return path.split('.').reduce((current, key) => current?.[key], obj);
    };

    const renderCell = (item, column) => {
        const value = getNestedValue(item, column.key);
        
        if (column.render) {
            return column.render(value, item);
        }

        if ((value === null || value === undefined || value === '') && column.key.includes('.')) {
            // fallback for computed relations (e.g. student.name, teacher.full_name)
            const [relation, field] = column.key.split('.');
            const relObject = getNestedValue(item, relation);

            if (relObject) {
                const fallback = relObject[field] || relObject[`${field}_name`] || relObject.name || relObject.full_name;
                if (fallback) return String(fallback);
            }
        }

        if (value === null || value === undefined || value === '') {
            return <span className="text-gray-300">—</span>;
        }

        if (column.key.includes('date') || column.key.includes('created_at') || column.key.includes('updated_at')) {
            return new Date(value).toLocaleDateString();
        }

        if (column.key.includes('time') && !column.key.includes('date')) {
            return new Date(`1970-01-01T${value}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        if (column.key === 'status') {
            const statusColors = {
                active: 'bg-deep-jungle-green text-platinum',
                inactive: 'bg-rich-black text-platinum',
                pending: 'bg-vivid-orange text-rich-black',
                completed: 'bg-platinum text-deep-jungle-green border border-deep-jungle-green/15'
            };
            const color = statusColors[value?.toLowerCase()] || 'bg-platinum text-deep-jungle-green border border-deep-jungle-green/15';
            return (
                <span className={`px-2 py-1 text-xs font-medium rounded-full ${color}`}>
                    {value}
                </span>
            );
        }

        return String(value);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-16">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-vivid-orange border-t-transparent"></div>
                <span className="ml-3 font-medium text-deep-jungle-green">Loading...</span>
            </div>
        );
    }

    if (!data || data.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-16 text-deep-jungle-green/70">
                <svg className="mb-4 h-16 w-16 text-vivid-orange/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p className="text-lg font-medium">No data available</p>
            </div>
        );
    }

    return (
        <div className="app-table-shell">
            <table className="min-w-full table-auto divide-y divide-deep-jungle-green/10">
                <thead className="bg-deep-jungle-green">
                    <tr>
                        {onSelect && (
                            <th className="px-4 py-4 text-left w-12">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                                    checked={selectedRows.size === data.length && data.length > 0}
                                    onChange={(e) => handleSelectAll(e.target.checked)}
                                />
                            </th>
                        )}
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className="cursor-pointer px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider text-platinum transition-colors hover:bg-white/5"
                                onClick={() => column.sortable !== false && handleSort(column.key)}
                            >
                                <div className="flex items-center space-x-1">
                                    <span>{column.label}</span>
                                    {column.sortable !== false && (
                                        <div className="flex flex-col ml-1">
                                            <ChevronUpIcon />
                                            <ChevronDownIcon />
                                        </div>
                                    )}
                                </div>
                            </th>
                        ))}
                        {(onEdit || onDelete) && (
                            <th className="w-24 px-4 py-4 text-right text-xs font-semibold uppercase tracking-wider text-platinum">
                                Actions
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-deep-jungle-green/10 bg-white">
                    {sortedData.map((item, index) => (
                        <tr 
                            key={item.id} 
                            className={`transition-colors hover:bg-vivid-orange/5 ${index % 2 === 0 ? 'bg-white' : 'bg-platinum/45'}`}
                        >
                            {onSelect && (
                                <td className="px-4 py-4">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                                        checked={selectedRows.has(item.id)}
                                        onChange={(e) => handleSelectRow(item.id, e.target.checked)}
                                    />
                                </td>
                            )}
                            {columns.map((column) => (
                                <td key={column.key} className="whitespace-nowrap px-4 py-4 text-sm text-deep-jungle-green">
                                    {renderCell(item, column)}
                                </td>
                            ))}
                            {(onEdit || onDelete) && (
                                <td className="px-4 py-4 whitespace-nowrap text-right">
                                    <div className="flex justify-end items-center space-x-1">
                                        {onEdit && (
                                            <button
                                                onClick={() => onEdit(item)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-platinum text-deep-jungle-green shadow-sm transition-all duration-200 hover:bg-vivid-orange/15 hover:text-rich-black"
                                                title="Edit"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        )}
                                        {onDelete && (
                                            <button
                                                onClick={() => onDelete(item)}
                                                className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-vivid-orange/15 text-rich-black shadow-sm transition-all duration-200 hover:bg-vivid-orange hover:text-rich-black"
                                                title="Delete"
                                            >
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        )}
                                    </div>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

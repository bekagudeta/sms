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

        if (value === null || value === undefined) {
            return <span className="text-gray-400">—</span>;
        }

        if (column.key.includes('date') || column.key.includes('created_at') || column.key.includes('updated_at')) {
            return new Date(value).toLocaleDateString();
        }

        if (column.key.includes('time') && !column.key.includes('date')) {
            return new Date(`1970-01-01T${value}`).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        if (column.key === 'status') {
            const statusColors = {
                active: 'bg-green-100 text-green-800',
                inactive: 'bg-red-100 text-red-800',
                pending: 'bg-yellow-100 text-yellow-800',
                completed: 'bg-blue-100 text-blue-800'
            };
            const color = statusColors[value?.toLowerCase()] || 'bg-gray-100 text-gray-800';
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
            <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span className="ml-2 text-gray-600">Loading...</span>
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <div className="text-center py-12">
                <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 className="mt-2 text-sm font-medium text-gray-900">No data</h3>
                <p className="mt-1 text-sm text-gray-500">Get started by creating a new item.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        {onSelect && (
                            <th className="px-6 py-3 text-left">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    checked={selectedRows.size === data.length && data.length > 0}
                                    onChange={(e) => handleSelectAll(e.target.checked)}
                                />
                            </th>
                        )}
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                onClick={() => column.sortable !== false && handleSort(column.key)}
                            >
                                <div className="flex items-center space-x-1">
                                    <span>{column.label}</span>
                                    {column.sortable !== false && (
                                        <div className="flex flex-col">
                                            <ChevronUpIcon
                                                className={`${
                                                    sortConfig.key === column.key && sortConfig.direction === 'asc'
                                                        ? 'text-blue-600'
                                                        : 'text-gray-400'
                                                }`}
                                            />
                                            <ChevronDownIcon
                                                className={`-mt-1 ${
                                                    sortConfig.key === column.key && sortConfig.direction === 'desc'
                                                        ? 'text-blue-600'
                                                        : 'text-gray-400'
                                                }`}
                                            />
                                        </div>
                                    )}
                                </div>
                            </th>
                        ))}
                        {(onEdit || onDelete) && (
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        )}
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {sortedData.map((item) => (
                        <tr key={item.id} className="hover:bg-gray-50">
                            {onSelect && (
                                <td className="px-6 py-4">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                        checked={selectedRows.has(item.id)}
                                        onChange={(e) => handleSelectRow(item.id, e.target.checked)}
                                    />
                                </td>
                            )}
                            {columns.map((column) => (
                                <td key={column.key} className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {renderCell(item, column)}
                                </td>
                            ))}
                            {(onEdit || onDelete) && (
                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div className="flex justify-end space-x-2">
                                        {onEdit && (
                                            <button
                                                onClick={() => onEdit(item)}
                                                className="text-blue-600 hover:text-blue-900"
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {onDelete && (
                                            <button
                                                onClick={() => onDelete(item)}
                                                className="text-red-600 hover:text-red-900"
                                            >
                                                Delete
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

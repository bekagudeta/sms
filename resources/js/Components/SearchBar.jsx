import React, { useState } from 'react';

export default function SearchBar({ onSearch, onFilter, config, initialFilters = {} }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [showFilters, setShowFilters] = useState(false);
    const [filters, setFilters] = useState(initialFilters);

    const handleSearchChange = (e) => {
        const value = e.target.value;
        setSearchTerm(value);
        onSearch(value);
    };

    const handleFilterChange = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        onFilter(newFilters);
    };

    const clearFilters = () => {
        const clearedFilters = {};
        setFilters(clearedFilters);
        onFilter(clearedFilters);
    };

    const getStatusOptions = () => {
        return [
            { value: 'active', label: 'Active' },
            { value: 'inactive', label: 'Inactive' },
            { value: 'pending', label: 'Pending' },
            { value: 'completed', label: 'Completed' }
        ];
    };

    const renderFilterField = (column) => {
        if (!column.filterable) return null;

        const filterValue = filters[column.key];

        switch (column.key) {
            case 'status':
                return (
                    <select
                        value={filterValue || ''}
                        onChange={(e) => handleFilterChange(column.key, e.target.value)}
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    >
                        <option value="">All Status</option>
                        {getStatusOptions().map(option => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                );

            case 'department':
                return (
                    <input
                        type="text"
                        value={filterValue || ''}
                        onChange={(e) => handleFilterChange(column.key, e.target.value)}
                        placeholder="Filter by department"
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    />
                );

            case 'day_of_week':
                return (
                    <select
                        value={filterValue || ''}
                        onChange={(e) => handleFilterChange(column.key, e.target.value)}
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    >
                        <option value="">All Days</option>
                        {['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map(day => (
                            <option key={day} value={day}>{day}</option>
                        ))}
                    </select>
                );

            case 'type':
                const typeOptions = config.routePrefix === 'rooms' ? 
                    ['lecture', 'lab', 'seminar', 'conference'] :
                    ['regular', 'lab', 'tutorial', 'seminar'];
                    
                return (
                    <select
                        value={filterValue || ''}
                        onChange={(e) => handleFilterChange(column.key, e.target.value)}
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    >
                        <option value="">All Types</option>
                        {typeOptions.map(type => (
                            <option key={type} value={type}>{type}</option>
                        ))}
                    </select>
                );

            default:
                return (
                    <input
                        type="text"
                        value={filterValue || ''}
                        onChange={(e) => handleFilterChange(column.key, e.target.value)}
                        placeholder={`Filter by ${column.label.toLowerCase()}`}
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    />
                );
        }
    };

    const filterableColumns = config.columns.filter(col => col.filterable);

    return (
        <div className="space-y-4">
            {/* Search Bar */}
            <div className="flex items-center space-x-4">
                <div className="flex-1 relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        value={searchTerm}
                        onChange={handleSearchChange}
                        placeholder={`Search ${config.title.toLowerCase()}...`}
                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm"
                    />
                </div>

                {filterableColumns.length > 0 && (
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className={`inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium ${
                            showFilters || Object.keys(filters).length > 0
                                ? 'border-blue-300 text-blue-700 bg-blue-50'
                                : 'border-gray-300 text-gray-700 bg-white'
                        }`}
                    >
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filters
                        {Object.keys(filters).length > 0 && (
                            <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                {Object.keys(filters).length}
                            </span>
                        )}
                    </button>
                )}
            </div>

            {/* Filter Panel */}
            {showFilters && (
                <div className="bg-gray-50 p-4 rounded-lg border">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-medium text-gray-900">Filter Options</h3>
                        {Object.keys(filters).length > 0 && (
                            <button
                                onClick={clearFilters}
                                className="text-sm text-blue-600 hover:text-blue-500"
                            >
                                Clear all
                            </button>
                        )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filterableColumns.map(column => (
                            <div key={column.key}>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    {column.label}
                                </label>
                                {renderFilterField(column)}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Active Filters Display */}
            {Object.keys(filters).length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {Object.entries(filters).map(([key, value]) => {
                        if (!value) return null;
                        const column = config.columns.find(col => col.key === key);
                        return (
                            <span
                                key={key}
                                className="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
                            >
                                {column?.label || key}: {value}
                                <button
                                    onClick={() => handleFilterChange(key, '')}
                                    className="ml-2 text-blue-600 hover:text-blue-500"
                                >
                                    ×
                                </button>
                            </span>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

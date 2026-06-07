import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function AuditLogsIndex() {
    const { logs, pagination, filters, filter_options, stats } = usePage().props;
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedFilters, setSelectedFilters] = useState({
        action: '',
        user_email: '',
        ip_address: '',
        date_from: '',
        date_to: '',
        sort_by: 'created_at',
        sort_order: 'desc'
    });
    const [isExporting, setIsExporting] = useState(false);
    const [expandedRows, setExpandedRows] = useState(new Set());

    const getActionBadgeClass = (action) => {
        const badgeMap = {
            'create': 'bg-green-100 text-green-800',
            'update': 'bg-blue-100 text-blue-800',
            'delete': 'bg-red-100 text-red-800',
            'login': 'bg-green-100 text-green-800',
            'failed_login': 'bg-red-100 text-red-800',
            'export': 'bg-purple-100 text-purple-800',
            'import': 'bg-orange-100 text-orange-800',
        };
        return badgeMap[action] || 'bg-gray-100 text-gray-800';
    };

    const getActionIcon = (action) => {
        const iconMap = {
            'create': '➕',
            'update': '✏️',
            'delete': '🗑️',
            'login': '🔓',
            'failed_login': '❌',
            'export': '📥',
            'import': '📤',
        };
        return iconMap[action] || '📋';
    };

    const toggleRowExpand = (logId) => {
        const newExpanded = new Set(expandedRows);
        if (newExpanded.has(logId)) {
            newExpanded.delete(logId);
        } else {
            newExpanded.add(logId);
        }
        setExpandedRows(newExpanded);
    };

    const isSuspiciousActivity = (log) => {
        // Check if failed login or unusual action
        if (log.action === 'failed_login') return true;
        if (['delete', 'export', 'import'].includes(log.action)) {
            const hour = new Date(log.created_at).getHours();
            if (hour >= 22 || hour <= 6) return true;
        }
        return false;
    };

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/audit-logs', {
            search: searchTerm,
            ...selectedFilters
        });
    };

    const handleFilterChange = (field, value) => {
        setSelectedFilters(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handleReset = () => {
        setSearchTerm('');
        setSelectedFilters({
            action: '',
            user_email: '',
            ip_address: '',
            date_from: '',
            date_to: '',
            sort_by: 'created_at',
            sort_order: 'desc'
        });
        // Reload the page with no filters
        window.location.href = '/audit-logs';
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            // Build query string from current filters
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (selectedFilters.action) params.append('action', selectedFilters.action);
            if (selectedFilters.user_email) params.append('user_email', selectedFilters.user_email);
            if (selectedFilters.date_from) params.append('date_from', selectedFilters.date_from);
            if (selectedFilters.date_to) params.append('date_to', selectedFilters.date_to);
            if (selectedFilters.ip_address) params.append('ip_address', selectedFilters.ip_address);
            if (selectedFilters.sort_by) params.append('sort_by', selectedFilters.sort_by);
            if (selectedFilters.sort_order) params.append('sort_order', selectedFilters.sort_order);
            
            // Trigger download by navigating to export endpoint
            window.location.href = `/audit-logs/export?${params.toString()}`;
        } finally {
            // Reset loading state after a short delay
            setTimeout(() => setIsExporting(false), 1000);
        }
    };

    const handlePageChange = (page) => {
        router.get(`/audit-logs?page=${page}`, {
            search: searchTerm,
            ...selectedFilters
        });
    };

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <DashboardLayout>
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <h1 className="text-3xl font-bold text-gray-900">Audit Logs</h1>
                        <p className="text-gray-600 mt-2">Monitor and track all system activities and changes</p>
                    </div>

                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white overflow-hidden shadow rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-1">
                                    <p className="text-sm text-gray-600">Total Logs</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.total_logs}</p>
                                </div>
                                <div className="text-3xl text-blue-500">📊</div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-1">
                                    <p className="text-sm text-gray-600">Today's Logs</p>
                                    <p className="text-2xl font-bold text-gray-900">{stats.todays_logs}</p>
                                </div>
                                <div className="text-3xl text-green-500">📈</div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-1">
                                    <p className="text-sm text-gray-600">Failed Logins</p>
                                    <p className="text-2xl font-bold text-red-600">{stats.failed_logins}</p>
                                </div>
                                <div className="text-3xl text-red-500">⚠️</div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-1">
                                    <p className="text-sm text-gray-600">Suspicious IPs</p>
                                    <p className="text-2xl font-bold text-orange-600">{stats.suspicious_ips_count}</p>
                                </div>
                                <div className="text-3xl text-orange-500">🔒</div>
                            </div>
                        </div>
                    </div>

                    {/* Search and Filters */}
                    <div className="bg-white overflow-hidden shadow rounded-lg p-6 mb-6">
                        <form onSubmit={handleSearch} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input
                                    type="text"
                                    placeholder="Search by action, user, IP, or description..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Action</label>
                                    <select
                                        value={selectedFilters.action}
                                        onChange={(e) => handleFilterChange('action', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    >
                                        <option value="">All Actions</option>
                                        {filter_options.actions?.map(action => (
                                            <option key={action} value={action}>{action}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">User Email</label>
                                    <select
                                        value={selectedFilters.user_email}
                                        onChange={(e) => handleFilterChange('user_email', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    >
                                        <option value="">All Users</option>
                                        {filter_options.users?.map(user => (
                                            <option key={user} value={user}>{user}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">IP Address</label>
                                    <select
                                        value={selectedFilters.ip_address}
                                        onChange={(e) => handleFilterChange('ip_address', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    >
                                        <option value="">All IPs</option>
                                        {filter_options.ips?.map(ip => (
                                            <option key={ip} value={ip}>{ip}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                                    <input
                                        type="date"
                                        value={selectedFilters.date_from}
                                        onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                                    <input
                                        type="date"
                                        value={selectedFilters.date_to}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                                    <select
                                        value={selectedFilters.sort_by}
                                        onChange={(e) => handleFilterChange('sort_by', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    >
                                        <option value="created_at">Date</option>
                                        <option value="action">Action</option>
                                        <option value="user_email">User</option>
                                        <option value="ip_address">IP Address</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Order</label>
                                    <select
                                        value={selectedFilters.sort_order}
                                        onChange={(e) => handleFilterChange('sort_order', e.target.value)}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg"
                                    >
                                        <option value="desc">Newest First</option>
                                        <option value="asc">Oldest First</option>
                                    </select>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <button
                                    type="submit"
                                    className="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 active:bg-blue-800 transition duration-200 shadow-sm"
                                >
                                    🔍 Search & Filter
                                </button>
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="px-6 py-2 bg-gray-400 text-white font-medium rounded-lg hover:bg-gray-500 active:bg-gray-600 transition duration-200 shadow-sm"
                                >
                                    ↻ Reset Filters
                                </button>
                                <button
                                    type="button"
                                    onClick={handleExport}
                                    disabled={isExporting}
                                    className={`px-6 py-2 font-medium rounded-lg transition duration-200 shadow-sm ${
                                        isExporting
                                            ? 'bg-green-400 text-white cursor-not-allowed opacity-75'
                                            : 'bg-green-600 text-white hover:bg-green-700 active:bg-green-800'
                                    }`}
                                >
                                    {isExporting ? '⏳ Exporting...' : '📥 Export CSV'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Results Summary */}
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-semibold text-gray-700">Audit Log Entries</p>
                            <p className="text-xs text-gray-500 mt-1">Showing <span className="font-bold text-gray-700">{logs.length}</span> of <span className="font-bold text-gray-700">{pagination.total}</span> total logs</p>
                        </div>
                    </div>

                    {/* Suspicious Activity Alert */}
                    {stats.suspicious_ips && stats.suspicious_ips.length > 0 && (
                        <div className="mb-6 bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 rounded-lg p-4 shadow-sm">
                            <div className="flex items-start">
                                <div className="flex-shrink-0 text-2xl">🚨</div>
                                <div className="ml-4 flex-1">
                                    <h3 className="text-sm font-bold text-red-900">Security Alert: Suspicious Activity Detected</h3>
                                    <p className="text-xs text-red-700 mt-1">
                                        The following IP addresses have more than {stats.suspicious_ips_threshold || 5} failed login attempts. Consider reviewing or blocking these IPs:
                                    </p>
                                    <div className="flex flex-wrap gap-2 mt-3">
                                        {stats.suspicious_ips.map(ip => (
                                            <button
                                                key={ip}
                                                onClick={() => {
                                                    handleFilterChange('ip_address', ip);
                                                    setTimeout(() => handleSearch({preventDefault: () => {}}), 0);
                                                }}
                                                className="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs font-medium transition duration-200 shadow-md hover:shadow-lg"
                                            >
                                                {ip}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Modern Audit Logs Table */}
                    <div className="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                        {/* Table Header */}
                        <div className="hidden lg:grid lg:grid-cols-12 gap-4 bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                            <div className="lg:col-span-2 flex items-center">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">Date & Time</span>
                            </div>
                            <div className="lg:col-span-2 flex items-center">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">User</span>
                            </div>
                            <div className="lg:col-span-2 flex items-center">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">Action</span>
                            </div>
                            <div className="lg:col-span-3 flex items-center">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">Description</span>
                            </div>
                            <div className="lg:col-span-2 flex items-center">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">IP & Device</span>
                            </div>
                            <div className="lg:col-span-1 flex items-center justify-end">
                                <span className="text-xs font-bold text-gray-600 uppercase tracking-widest">Status</span>
                            </div>
                        </div>

                        {/* Table Body */}
                        <div className="divide-y divide-gray-100">
                            {logs && logs.length > 0 ? (
                                logs.map((log, index) => {
                                    const isExpanded = expandedRows.has(log.id);
                                    const isSuspicious = isSuspiciousActivity(log);
                                    const riskLevel = isSuspicious ? 'High' : 'Low';
                                    
                                    return (
                                        <React.Fragment key={log.id}>
                                            {/* Main Row */}
                                            <div className={`px-6 py-4 transition duration-200 ${isSuspicious ? 'bg-red-50 hover:bg-red-100' : index % 2 === 0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100'} cursor-pointer`} onClick={() => toggleRowExpand(log.id)}>
                                                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                                                    {/* Date & Time */}
                                                    <div className="lg:col-span-2">
                                                        <p className="text-sm font-medium text-gray-900">{formatDate(log.created_at).split(',')[0]}</p>
                                                        <p className="text-xs text-gray-500">{formatDate(log.created_at).split(',')[1]}</p>
                                                    </div>

                                                    {/* User */}
                                                    <div className="lg:col-span-2">
                                                        <p className="text-sm font-medium text-gray-900 truncate" title={log.user_email}>{log.user_email}</p>
                                                    </div>

                                                    {/* Action Badge */}
                                                    <div className="lg:col-span-2 flex items-center">
                                                        <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold ${getActionBadgeClass(log.action)} shadow-sm`}>
                                                            <span>{getActionIcon(log.action)}</span>
                                                            {log.action.replace('_', ' ')}
                                                        </span>
                                                    </div>

                                                    {/* Description */}
                                                    <div className="lg:col-span-3">
                                                        <p className="text-sm text-gray-700 line-clamp-2 truncate" title={log.description || 'No description'}>{log.description || '—'}</p>
                                                    </div>

                                                    {/* IP & Device */}
                                                    <div className="lg:col-span-2">
                                                        <p className="text-sm font-mono text-gray-900 truncate" title={log.ip_address}>{log.ip_address}</p>
                                                        <p className="text-xs text-gray-500 truncate" title={log.user_agent}>{log.user_agent || 'Unknown'}</p>
                                                    </div>

                                                    {/* Status Badge */}
                                                    <div className="lg:col-span-1 flex items-center justify-end">
                                                        {isSuspicious ? (
                                                            <span className="inline-flex items-center gap-1 px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold shadow-sm">
                                                                <span>⚠️</span>
                                                                <span>High Risk</span>
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-xs font-semibold shadow-sm">
                                                                <span>✓</span>
                                                                <span>Normal</span>
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Expanded Details Row */}
                                            {isExpanded && (
                                                <div className={`px-6 py-4 border-t border-gray-200 ${isSuspicious ? 'bg-red-100' : 'bg-gray-100'}`}>
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                        {/* Left Column */}
                                                        <div className="space-y-3">
                                                            <div>
                                                                <p className="text-xs font-bold text-gray-600 uppercase">Full User Email</p>
                                                                <p className="text-sm font-mono text-gray-900 break-all">{log.user_email}</p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-bold text-gray-600 uppercase">IP Address</p>
                                                                <p className="text-sm font-mono text-gray-900">{log.ip_address}</p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-bold text-gray-600 uppercase">Full Description</p>
                                                                <p className="text-sm text-gray-700 break-words">{log.description || '—'}</p>
                                                            </div>
                                                        </div>

                                                        {/* Right Column */}
                                                        <div className="space-y-3">
                                                            <div>
                                                                <p className="text-xs font-bold text-gray-600 uppercase">User Agent (Device)</p>
                                                                <p className="text-sm text-gray-700 break-words">{log.user_agent || '—'}</p>
                                                            </div>
                                                            <div>
                                                                <p className="text-xs font-bold text-gray-600 uppercase">Entity Information</p>
                                                                <p className="text-sm text-gray-700"><strong>Type:</strong> {log.model_type || '—'} | <strong>ID:</strong> {log.model_id || '—'}</p>
                                                            </div>
                                                            {log.changes && (
                                                                <div>
                                                                    <p className="text-xs font-bold text-gray-600 uppercase">Changes Made</p>
                                                                    <pre className="text-xs bg-white rounded p-2 border border-gray-300 overflow-x-auto max-h-32 text-gray-700">{JSON.stringify(log.changes, null, 2)}</pre>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Expand/Collapse Indicator */}
                                                    <div className="mt-3 text-xs text-gray-600 text-center">
                                                        Click to collapse details
                                                    </div>
                                                </div>
                                            )}
                                        </React.Fragment>
                                    );
                                })
                            ) : (
                                <div className="px-6 py-12 text-center">
                                    <div className="text-4xl mb-3">📭</div>
                                    <p className="text-gray-500 font-medium">No audit logs found</p>
                                    <p className="text-gray-400 text-sm mt-1">Try adjusting your search filters or date range</p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Pagination */}
                    {pagination && pagination.total > pagination.per_page && (
                        <div className="mt-8 flex items-center justify-between bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-gray-700">Page</span>
                                <span className="text-sm font-bold text-blue-600 bg-blue-50 px-3 py-1 rounded">{pagination.current_page}</span>
                                <span className="text-sm text-gray-500">of</span>
                                <span className="text-sm font-bold text-gray-700 bg-gray-50 px-3 py-1 rounded">{pagination.last_page}</span>
                                <span className="text-xs text-gray-500 ml-2">({pagination.total} total logs)</span>
                            </div>
                            
                            <div className="flex gap-1">
                                {pagination.current_page > 1 && (
                                    <button
                                        onClick={() => handlePageChange(pagination.current_page - 1)}
                                        className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-100 transition duration-200"
                                    >
                                        ← Previous
                                    </button>
                                )}

                                {/* Page numbers */}
                                {Array.from({ length: pagination.last_page }, (_, i) => i + 1).map(page => {
                                    if (
                                        page === 1 ||
                                        page === pagination.last_page ||
                                        (page >= pagination.current_page - 1 && page <= pagination.current_page + 1)
                                    ) {
                                        return (
                                            <button
                                                key={page}
                                                onClick={() => handlePageChange(page)}
                                                className={`px-3 py-2 rounded-lg font-medium transition duration-200 ${
                                                    page === pagination.current_page
                                                        ? 'bg-blue-600 text-white shadow-md'
                                                        : 'border border-gray-300 text-gray-700 hover:bg-gray-100'
                                                }`}
                                            >
                                                {page}
                                            </button>
                                        );
                                    } else if (
                                        page === pagination.current_page - 2 ||
                                        page === pagination.current_page + 2
                                    ) {
                                        return (
                                            <span key={page} className="px-2 py-2 text-gray-400">
                                                •••
                                            </span>
                                        );
                                    }
                                    return null;
                                })}

                                {pagination.current_page < pagination.last_page && (
                                    <button
                                        onClick={() => handlePageChange(pagination.current_page + 1)}
                                        className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-100 transition duration-200"
                                    >
                                        Next →
                                    </button>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </DashboardLayout>
    );
}

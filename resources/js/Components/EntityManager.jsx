import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { getEntityConfig } from '../config/entities';
import DataTable from './DataTable';
import ImportModal from './ImportModal';
import EntityForm from './EntityForm';
import SearchBar from './SearchBar';
import BulkActions from './BulkActions';

export default function EntityManager({ entityType, initialData = [], filters = {}, permissions = {} }) {
    const config = getEntityConfig(entityType);
    const { props } = usePage();
    const flash = props.flash;


    const [data, setData] = useState(initialData?.data || initialData || []);
    const [pagination, setPagination] = useState(initialData?.current_page ? {
        current_page: initialData.current_page,
        last_page: initialData.last_page,
        per_page: initialData.per_page,
        total: initialData.total,
        from: initialData.from,
        to: initialData.to
    } : null);
    const [selectedItems, setSelectedItems] = useState([]);
    const [showImportModal, setShowImportModal] = useState(false);
    const [showFormModal, setShowFormModal] = useState(false);
    const [editingItem, setEditingItem] = useState(null);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [activeFilters, setActiveFilters] = useState(filters);

    const { data: formData, setData: setFormData, post, put, delete: destroy, processing, errors, reset } = useForm({
        ...config.requiredColumns.reduce((acc, col) => ({ ...acc, [col]: '' }), {}),
        ...config.optionalColumns.reduce((acc, col) => ({ ...acc, [col]: '' }), {})
    });

    useEffect(() => {
        if (initialData?.data) {
            setData(initialData.data);
            setPagination({
                current_page: initialData.current_page,
                last_page: initialData.last_page,
                per_page: initialData.per_page,
                total: initialData.total,
                from: initialData.from,
                to: initialData.to
            });
        } else {
            setData(initialData || []);
            setPagination(null);
        }
    }, [initialData]);

    const handleCreate = () => {
        setEditingItem(null);
        reset();
        setShowFormModal(true);
    };

    const handleEdit = (item) => {
        setEditingItem(item);
        Object.keys(formData).forEach(key => {
            setFormData(key, item[key] || '');
        });
        setShowFormModal(true);
    };

    const handleDelete = async (item) => {
        if (!confirm(`Are you sure you want to delete this ${config.singular}?`)) {
            return;
        }

        try {
            setLoading(true);
            router.delete(`/entities/${entityType}/${item.id}`, {
                onSuccess: () => {
                    setData(prev => prev.filter(d => d.id !== item.id));
                    setSelectedItems(prev => prev.filter(id => id !== item.id));
                },
                onError: (errors) => {
                    console.error('Delete error:', errors);
                },
                onFinish: () => setLoading(false)
            });
        } catch (error) {
            console.error('Delete failed:', error);
            setLoading(false);
        }
    };

    const handleBulkDelete = async () => {
        if (selectedItems.length === 0) return;
        
        if (!confirm(`Are you sure you want to delete ${selectedItems.length} ${config.title.toLowerCase()}?`)) {
            return;
        }

        try {
            setLoading(true);
            router.post(`/entities/${entityType}/bulk-delete`, {
                ids: selectedItems
            }, {
                onSuccess: () => {
                    setData(prev => prev.filter(d => !selectedItems.includes(d.id)));
                    setSelectedItems([]);
                },
                onError: (errors) => {
                    console.error('Bulk delete error:', errors);
                },
                onFinish: () => setLoading(false)
            });
        } catch (error) {
            console.error('Bulk delete failed:', error);
            setLoading(false);
        }
    };

    const handleFormSubmit = (e) => {
        e.preventDefault();

        const submitData = editingItem 
            ? put(`/entities/${entityType}/${editingItem.id}`, formData)
            : post(`/entities/${entityType}`, formData);

        submitData.then(() => {
            setShowFormModal(false);
            reset();
            // Refresh data
            router.reload({ only: ['data'] });
        }).catch(error => {
            console.error('Form submission error:', error);
        });
    };

    const handleSearch = (term) => {
        setSearchTerm(term);
        // Implement search logic
        router.get(`/entities/${entityType}`, { search: term, ...activeFilters }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleFilter = (newFilters) => {
        setActiveFilters(newFilters);
        router.get(`/entities/${entityType}`, { search: searchTerm, ...newFilters }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleExport = () => {
        window.open(`/entities/${entityType}/export?${new URLSearchParams({ ...activeFilters, search: searchTerm })}`);
    };

    const canPerformAction = (action) => {
        return !!permissions[action];
    };

    return (
        <div className="entity-manager">
            <Head title={config.title} />

            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div className="px-6 py-5">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <div className="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200">
                                <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-gray-900">{config.title}</h1>
                                <p className="text-sm text-gray-500 mt-0.5">
                                    Manage {config.title.toLowerCase()} and their properties
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-center space-x-3">
                            {canPerformAction('import') && (
                                <button
                                    onClick={() => setShowImportModal(true)}
                                    className="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200"
                                >
                                    <svg className="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Import
                                </button>
                            )}
                            
                            {canPerformAction('create') && (
                                <button
                                    onClick={handleCreate}
                                    className="inline-flex items-center px-4 py-2.5 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 transition-all duration-200 hover:shadow-lg"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Add {config.singular}
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Flash Messages */}
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    {flash.error}
                </div>
            )}

            {/* Search and Filters */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div className="px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div className="flex-1 max-w-lg">
                            <div className="relative">
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    placeholder={`Search ${config.title.toLowerCase()}...`}
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    onKeyPress={(e) => e.key === 'Enter' && handleSearch(searchTerm)}
                                    className="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out sm:text-sm"
                                />
                            </div>
                        </div>
                        
                        <div className="flex items-center space-x-3 ml-4">
                            <button className="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <svg className="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Bulk Actions */}
            {selectedItems.length > 0 && (
                <div className="bg-blue-50 border-b">
                    <div className="px-6 py-3">
                        <BulkActions
                            selectedCount={selectedItems.length}
                            onDelete={handleBulkDelete}
                            onExport={handleExport}
                            canDelete={canPerformAction('delete')}
                            canExport={canPerformAction('export')}
                        />
                    </div>
                </div>
            )}

            {/* Data Table */}
            <div className="bg-white">
                <DataTable
                    columns={config.columns}
                    data={data}
                    onEdit={canPerformAction('edit') ? handleEdit : null}
                    onDelete={canPerformAction('delete') ? handleDelete : null}
                    onSelect={setSelectedItems}
                    loading={loading}
                />
            </div>

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="bg-white px-6 py-4 border-t border-gray-200">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-gray-600">
                            Showing <span className="font-medium text-gray-900">{pagination.from}</span> to <span className="font-medium text-gray-900">{pagination.to}</span> of <span className="font-medium text-gray-900">{pagination.total}</span> results
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                onClick={() => router.get(`/entities/${entityType}?page=1`, { preserveState: true })}
                                disabled={pagination.current_page === 1}
                                className="px-2 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded transition hover:bg-gray-50 disabled:text-gray-300 disabled:bg-gray-100"
                            >
                                First
                            </button>
                            <button
                                onClick={() => router.get(`/entities/${entityType}?page=${Math.max(1, pagination.current_page - 1)}`, { preserveState: true })}
                                disabled={pagination.current_page === 1}
                                className="px-2 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded transition hover:bg-gray-50 disabled:text-gray-300 disabled:bg-gray-100"
                            >
                                Prev
                            </button>

                            {(() => {
                                const pageButtons = [];
                                const total = pagination.last_page;
                                const current = pagination.current_page;
                                const low = Math.max(1, current - 2);
                                const high = Math.min(total, current + 2);

                                if (low > 1) {
                                    pageButtons.push(
                                        <button key={1} onClick={() => router.get(`/entities/${entityType}?page=1`, { preserveState: true })} className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50">1</button>
                                    );
                                    if (low > 2) {
                                        pageButtons.push(<span key="start-ellipsis" className="px-2 text-sm text-gray-400">...</span>);
                                    }
                                }

                                for (let page = low; page <= high; page++) {
                                    pageButtons.push(
                                        <button
                                            key={page}
                                            onClick={() => router.get(`/entities/${entityType}?page=${page}`, { preserveState: true })}
                                            className={`px-3 py-1.5 text-sm font-medium rounded ${page === current ? 'bg-blue-600 text-white border border-blue-600' : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'}`}
                                        >
                                            {page}
                                        </button>
                                    );
                                }

                                if (high < total) {
                                    if (high < total - 1) {
                                        pageButtons.push(<span key="end-ellipsis" className="px-2 text-sm text-gray-400">...</span>);
                                    }
                                    pageButtons.push(
                                        <button key={total} onClick={() => router.get(`/entities/${entityType}?page=${total}`, { preserveState: true })} className="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded hover:bg-gray-50">{total}</button>
                                    );
                                }

                                return pageButtons;
                            })()}

                            <button
                                onClick={() => router.get(`/entities/${entityType}?page=${Math.min(pagination.last_page, pagination.current_page + 1)}`, { preserveState: true })}
                                disabled={pagination.current_page === pagination.last_page}
                                className="px-2 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded transition hover:bg-gray-50 disabled:text-gray-300 disabled:bg-gray-100"
                            >
                                Next
                            </button>
                            <button
                                onClick={() => router.get(`/entities/${entityType}?page=${pagination.last_page}`, { preserveState: true })}
                                disabled={pagination.current_page === pagination.last_page}
                                className="px-2 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-200 rounded transition hover:bg-gray-50 disabled:text-gray-300 disabled:bg-gray-100"
                            >
                                Last
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Import Modal */}
            {showImportModal && (
                <ImportModal
                    isOpen={showImportModal}
                    onClose={() => setShowImportModal(false)}
                    entityType={entityType}
                    config={config}
                    onSuccess={() => {
                        setShowImportModal(false);
                        router.reload({ only: ['data'] });
                    }}
                />
            )}

            {/* Form Modal */}
            {showFormModal && (
                <EntityForm
                    isOpen={showFormModal}
                    onClose={() => setShowFormModal(false)}
                    config={config}
                    formData={formData}
                    setFormData={setFormData}
                    errors={errors}
                    processing={processing}
                    editingItem={editingItem}
                    onSubmit={handleFormSubmit}
                />
            )}
        </div>
    );
}

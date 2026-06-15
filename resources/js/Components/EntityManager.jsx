import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { getEntityConfig } from '../config/entities';
import DataTable from './DataTable';
import ImportModal from './ImportModal';
import EntityForm from './EntityForm';
import SearchBar from './SearchBar';
import BulkActions from './BulkActions';

export default function EntityManager({ entityType, initialData = [], filters = {}, permissions = {}, relatedOptions = {} }) {
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

    // Seed the form state from the explicit formFields when available (these are
    // decoupled from import columns); otherwise fall back to import columns.
    const initialFormState = config.formFields?.length
        ? config.formFields.reduce(
              (acc, field) => ({ ...acc, [field.name]: field.type === 'checkbox' ? false : '' }),
              {}
          )
        : {
              ...config.requiredColumns.reduce((acc, col) => ({ ...acc, [col]: '' }), {}),
              ...config.optionalColumns.reduce((acc, col) => ({ ...acc, [col]: '' }), {}),
          };

    const { data: formData, setData: setFormData, post, put, delete: destroy, processing, errors, reset } = useForm(initialFormState);

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

    const toTimeInputValue = (value) => {
        if (!value) return '';

        // if already HH:MM
        if (/^\d{2}:\d{2}$/.test(value)) return value;

        // if HH:MM:SS -> HH:MM
        if (/^\d{2}:\d{2}:\d{2}$/.test(value)) {
            return value.slice(0, 5);
        }

        // parse 12-hour values like "02:00 AM" into HH:MM
        const twelve = value.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (twelve) {
            let hour = Number(twelve[1]);
            const minute = twelve[2];
            const meridian = twelve[3].toUpperCase();

            if (meridian === 'PM' && hour < 12) hour += 12;
            if (meridian === 'AM' && hour === 12) hour = 0;

            return `${String(hour).padStart(2, '0')}:${minute}`;
        }

        return value;
    };

    const handleEdit = (item) => {
        setEditingItem(item);
        Object.keys(formData).forEach(key => {
            let value = item[key] ?? '';

            if (['start_time', 'end_time'].includes(key)) {
                value = toTimeInputValue(value);
            }

            if (['start_date', 'end_date'].includes(key) && value) {
                // Normalize date values for HTML date inputs
                if (/^\d{4}-\d{2}-\d{2}T/.test(value)) {
                    value = value.slice(0, 10);
                } else if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) {
                    value = value.slice(0, 10);
                }
            }

            setFormData(key, value);
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

        const payload = { ...formData };
        if (entityType === 'timeslots') {
            const formatTime = (t) => {
                if (!t) return t;
                if (/^\d{2}:\d{2}$/.test(t)) return t;
                if (/^\d{2}:\d{2}:\d{2}$/.test(t)) return t.slice(0, 5);

                const twelve = t.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
                if (twelve) {
                    let hour = Number(twelve[1]);
                    const minute = twelve[2];
                    const meridian = twelve[3].toUpperCase();
                    if (meridian === 'PM' && hour < 12) hour += 12;
                    if (meridian === 'AM' && hour === 12) hour = 0;
                    return `${String(hour).padStart(2, '0')}:${minute}`;
                }

                return t;
            };

            payload.start_time = formatTime(payload.start_time);
            payload.end_time = formatTime(payload.end_time);
        }

        setLoading(true);

        const handleSuccess = () => {
            if (editingItem) {
                setData((prev) => prev.map((item) =>
                    item.id === editingItem.id ? { ...item, ...payload } : item
                ));
            }

            setShowFormModal(false);
            reset();
            router.reload();
        };

        const handleError = (errorData) => {
            console.error('Form submission error:', errorData);
        };

        const handleFinish = () => {
            setLoading(false);
        };

        if (editingItem) {
            put(`/entities/${entityType}/${editingItem.id}`, payload, {
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: handleFinish
            });
        } else {
            post(`/entities/${entityType}`, payload, {
                onSuccess: handleSuccess,
                onError: handleError,
                onFinish: handleFinish
            });
        }
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
            <div className="app-panel mb-6 overflow-hidden">
                <div className="px-6 py-5">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-vivid-orange text-rich-black shadow-sm">
                                <svg className="h-6 w-6 text-rich-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold text-deep-jungle-green">{config.title}</h1>
                                <p className="mt-0.5 text-sm text-deep-jungle-green/65">
                                    Manage {config.title.toLowerCase()} and their properties
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-center space-x-3">
                            {canPerformAction('import') && (
                                <button
                                    onClick={() => setShowImportModal(true)}
                                    className="app-secondary-btn"
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
                                    className="app-primary-btn"
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
                <div className="mb-4 rounded-2xl border border-vivid-orange/30 bg-white px-4 py-3 text-deep-jungle-green shadow-sm">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 rounded-2xl border border-rich-black/20 bg-white px-4 py-3 text-deep-jungle-green shadow-sm">
                    {flash.error}
                </div>
            )}

            {/* Search and Filters */}
            <div className="app-panel mb-6 overflow-hidden">
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
                                    className="app-input block w-full pl-10 pr-3 py-2.5 leading-5 transition duration-150 ease-in-out sm:text-sm"
                                />
                            </div>
                        </div>
                        
                        <div className="flex items-center space-x-3 ml-4">
                            <button className="app-secondary-btn">
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
                <div className="mb-6 rounded-2xl border border-vivid-orange/20 bg-vivid-orange/10">
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
            <div className="app-panel overflow-hidden">
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
                    relatedOptions={relatedOptions}
                    onSubmit={handleFormSubmit}
                />
            )}
        </div>
    );
}

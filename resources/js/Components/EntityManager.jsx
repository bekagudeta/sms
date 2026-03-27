import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { getEntityConfig } from '@/config/entities';
import DataTable from './DataTable';
import ImportModal from './ImportModal';
import EntityForm from './EntityForm';
import SearchBar from './SearchBar';
import BulkActions from './BulkActions';

export default function EntityManager({ entityType, initialData = [], filters = {}, permissions = {} }) {
    const config = getEntityConfig(entityType);
    const { props } = usePage();
    const flash = props.flash;

    console.log('EntityManager Debug:', {
        entityType,
        initialData,
        permissions,
        config
    });

    const [data, setData] = useState(initialData);
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
        setData(initialData);
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
            router.delete(`/${config.routePrefix}/${item.id}`, {
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
            router.post(`/${config.routePrefix}/bulk-delete`, {
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
            ? put(`/${config.routePrefix}/${editingItem.id}`, formData)
            : post(`/${config.routePrefix}`, formData);

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
        router.get(`/${config.routePrefix}`, { search: term, ...activeFilters }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleFilter = (newFilters) => {
        setActiveFilters(newFilters);
        router.get(`/${config.routePrefix}`, { search: searchTerm, ...newFilters }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const handleExport = () => {
        window.open(`/${config.routePrefix}/export?${new URLSearchParams({ ...activeFilters, search: searchTerm })}`);
    };

    const canPerformAction = (action) => {
        return permissions[config.permissions[action]] || false;
    };

    return (
        <div className="entity-manager">
            <Head title={config.title} />

            {/* Debug Info */}
            <div className="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded m-4">
                <strong>Debug Info:</strong>
                <br />Entity Type: {entityType}
                <br />Data Count: {Array.isArray(data) ? data.length : (data?.data?.length || 0)}
                <br />Config: {config ? 'Loaded' : 'Missing'}
                <br />Permissions: {Object.keys(permissions).length > 0 ? 'Loaded' : 'Missing'}
            </div>

            {/* Header */}
            <div className="bg-white shadow-sm border-b">
                <div className="px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">{config.title}</h1>
                            <p className="text-sm text-gray-500 mt-1">
                                Manage {config.title.toLowerCase()} and their properties
                            </p>
                        </div>
                        
                        <div className="flex items-center space-x-3">
                            {canPerformAction('import') && (
                                <button
                                    onClick={() => setShowImportModal(true)}
                                    className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Import {config.title}
                                </button>
                            )}
                            
                            {canPerformAction('create') && (
                                <button
                                    onClick={handleCreate}
                                    className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
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

            {/* Simple Data Display */}
            <div className="bg-white p-6">
                <h2 className="text-lg font-semibold mb-4">Data Preview</h2>
                {Array.isArray(data) && data.length > 0 ? (
                    <div className="bg-green-50 p-4 rounded">
                        <p>Found {data.length} items</p>
                        <pre className="text-xs mt-2">{JSON.stringify(data.slice(0, 2), null, 2)}</pre>
                    </div>
                ) : data?.data && Array.isArray(data.data) ? (
                    <div className="bg-green-50 p-4 rounded">
                        <p>Found {data.data.length} items</p>
                        <pre className="text-xs mt-2">{JSON.stringify(data.data.slice(0, 2), null, 2)}</pre>
                    </div>
                ) : (
                    <div className="bg-gray-50 p-4 rounded">
                        <p>No data available. This is normal if the database is empty.</p>
                        <p>Use the "Add {config.singular}" button above to create the first item.</p>
                    </div>
                )}
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
            <div className="bg-white shadow-sm border-b">
                <div className="px-6 py-4">
                    <SearchBar
                        onSearch={handleSearch}
                        onFilter={handleFilter}
                        config={config}
                        initialFilters={activeFilters}
                    />
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

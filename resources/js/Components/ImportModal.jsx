import React, { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';

export default function ImportModal({ isOpen, onClose, entityType, config, onSuccess }) {
    const fileInputRef = useRef(null);
    const [dragActive, setDragActive] = useState(false);
    const [previewData, setPreviewData] = useState([]);
    const [step, setStep] = useState(1); // 1: file selection, 2: preview, 3: processing
    
    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        entity_type: entityType,
        mappings: {},
        skip_header: true
    });

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFile(e.dataTransfer.files[0]);
        }
    };

    const handleFileSelect = (e) => {
        if (e.target.files && e.target.files[0]) {
            handleFile(e.target.files[0]);
        }
    };

    const handleFile = async (file) => {
        if (!file) return;

        const validTypes = ['.csv', '.xlsx', '.xls'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
        
        if (!validTypes.includes(fileExtension)) {
            alert('Please upload a CSV or Excel file');
            return;
        }

        setData('file', file);

        // For CSV files, read and preview
        if (fileExtension === '.csv') {
            try {
                const text = await file.text();
                const lines = text.split('\n').filter(line => line.trim());
                const headers = lines[0]?.split(',').map(h => h.trim().replace(/"/g, ''));
                const data = lines.slice(1, 6).map(line => 
                    line.split(',').map(cell => cell.trim().replace(/"/g, ''))
                );

                setPreviewData({ headers, data });
                setStep(2);
            } catch (error) {
                console.error('Error reading file:', error);
                alert('Error reading file. Please check the file format.');
            }
        } else {
            // For Excel files, just show file info
            setPreviewData({ 
                headers: ['Column 1', 'Column 2', 'Column 3'], 
                data: [['Sample', 'Data', 'Here']] 
            });
            setStep(2);
        }
    };

    const handleMappingChange = (requiredColumn, fileColumn) => {
        setData('mappings', {
            ...data.mappings,
            [requiredColumn]: fileColumn
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('file', data.file);
        formData.append('entity_type', data.entity_type);
        formData.append('skip_header', data.skip_header ? '1' : '0');
        
        Object.entries(data.mappings).forEach(([key, value]) => {
            formData.append(`mappings[${key}]`, value);
        });

        post('/import/bulk', {
            data: formData,
            onSuccess: () => {
                onSuccess();
                handleClose();
            },
            onError: (errors) => {
                console.error('Import errors:', errors);
                alert('Import failed. Please check the file and mappings.');
            }
        });
    };

    const handleClose = () => {
        setStep(1);
        setPreviewData([]);
        setSelectedFile(null);
        reset();
        onClose();
    };

    const renderFileSelection = () => (
        <div>
            <div
                className={`relative border-2 border-dashed rounded-lg p-6 text-center ${
                    dragActive ? 'border-blue-400 bg-blue-50' : 'border-gray-300'
                }`}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
            >
                <input
                    ref={fileInputRef}
                    type="file"
                    className="hidden"
                    accept=".csv,.xlsx,.xls"
                    onChange={handleFileSelect}
                />
                
                <div className="space-y-4">
                    <div className="mx-auto h-12 w-12 text-gray-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </div>
                    
                    <div>
                        <p className="text-lg font-medium text-gray-900">
                            Drop your file here, or{' '}
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="text-blue-600 hover:text-blue-500"
                            >
                                browse
                            </button>
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            Supports CSV, Excel files (.csv, .xlsx, .xls)
                        </p>
                    </div>
                </div>
            </div>

            <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-2">Required Columns:</h4>
                <div className="text-sm text-gray-600">
                    {config.requiredColumns.map(col => (
                        <span key={col} className="inline-block px-2 py-1 bg-blue-100 text-blue-800 rounded mr-2 mb-2">
                            {col}
                        </span>
                    ))}
                </div>
                {config.optionalColumns.length > 0 && (
                    <>
                        <h4 className="font-medium text-gray-900 mt-4 mb-2">Optional Columns:</h4>
                        <div className="text-sm text-gray-600">
                            {config.optionalColumns.map(col => (
                                <span key={col} className="inline-block px-2 py-1 bg-gray-200 text-gray-800 rounded mr-2 mb-2">
                                    {col}
                                </span>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );

    const renderPreview = () => (
        <div>
            <div className="mb-4">
                <h3 className="text-lg font-medium text-gray-900">File Preview</h3>
                <p className="text-sm text-gray-500">Map your file columns to the required fields</p>
            </div>

            <div className="mb-6">
                <label className="flex items-center">
                    <input
                        type="checkbox"
                        checked={data.skip_header}
                        onChange={(e) => setData('skip_header', e.target.checked)}
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span className="ml-2 text-sm text-gray-700">Skip first row (header)</span>
                </label>
            </div>

            <div className="space-y-4">
                {config.requiredColumns.map(requiredCol => (
                    <div key={requiredCol} className="flex items-center space-x-4">
                        <div className="w-32">
                            <label className="block text-sm font-medium text-gray-700">
                                {requiredCol} *
                            </label>
                        </div>
                        <div className="flex-1">
                            <select
                                value={data.mappings[requiredCol] || ''}
                                onChange={(e) => handleMappingChange(requiredCol, e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select column...</option>
                                {previewData.headers?.map((header, index) => (
                                    <option key={index} value={index}>
                                        Column {index + 1}: {header}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                ))}
            </div>

            {previewData.data?.length > 0 && (
                <div className="mt-6">
                    <h4 className="font-medium text-gray-900 mb-2">Data Preview (First 5 rows):</h4>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    {previewData.headers?.map((header, index) => (
                                        <th key={index} className="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase">
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {previewData.data.map((row, rowIndex) => (
                                    <tr key={rowIndex}>
                                        {row.map((cell, cellIndex) => (
                                            <td key={cellIndex} className="px-2 py-1 text-gray-900">
                                                {cell}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-center justify-center min-h-screen px-4">
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75" onClick={handleClose}></div>
                
                <div className="relative bg-white rounded-lg max-w-4xl w-full p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-semibold text-gray-900">
                            Import {config.title}
                        </h2>
                        <button
                            onClick={handleClose}
                            className="text-gray-400 hover:text-gray-500"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        {step === 1 && renderFileSelection()}
                        {step === 2 && renderPreview()}

                        <div className="flex justify-between mt-6">
                            <button
                                type="button"
                                onClick={step === 2 ? () => setStep(1) : handleClose}
                                className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                {step === 2 ? 'Back' : 'Cancel'}
                            </button>
                            
                            {step === 1 && data.file && (
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                                >
                                    Next
                                </button>
                            )}
                            
                            {step === 2 && (
                                <button
                                    type="submit"
                                    disabled={processing || Object.keys(data.mappings).length !== config.requiredColumns.length}
                                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400"
                                >
                                    {processing ? 'Importing...' : 'Import Data'}
                                </button>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

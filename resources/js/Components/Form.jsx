import React from 'react';

export default function Form({ 
    fields, 
    data, 
    setData, 
    errors, 
    onSubmit, 
    processing, 
    submitText = 'Submit' 
}) {
    const renderField = (field) => {
        const { name, label, type = 'text', options = [], required = false, placeholder = '' } = field;
        const value = data[name] || '';

        switch (type) {
            case 'textarea':
                return (
                    <textarea
                        id={name}
                        value={value}
                        onChange={e => setData(name, e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        rows="3"
                        placeholder={placeholder}
                    />
                );
            
            case 'select':
                return (
                    <select
                        id={name}
                        value={value}
                        onChange={e => setData(name, e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                        <option value="">Select {label}</option>
                        {options.map(option => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                );
            
            case 'checkbox':
                return (
                    <input
                        type="checkbox"
                        id={name}
                        checked={value}
                        onChange={e => setData(name, e.target.checked)}
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    />
                );
            
            default:
                return (
                    <input
                        type={type}
                        id={name}
                        value={value}
                        onChange={e => setData(name, e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        placeholder={placeholder}
                    />
                );
        }
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {fields.map((field) => (
                    <div key={field.name} className="mb-4">
                    <label 
                        htmlFor={field.name} 
                        className="block text-sm font-medium text-gray-700"
                    >
                        {field.label}
                        {field.required && <span className="text-red-500 ml-1">*</span>}
                    </label>
                    
                    {renderField(field)}
                    
                    {errors[field.name] && (
                        <div className="text-red-500 text-sm mt-1">
                            {errors[field.name]}
                        </div>
                    )}
                </div>
            ))}
            </div>

            <div className="flex items-center justify-end mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline disabled:opacity-50"
                >
                    {processing ? 'Processing...' : submitText}
                </button>
            </div>
        </form>
    );
}
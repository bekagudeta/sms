import React from 'react';

export default function Button({ 
    children, 
    type = 'button', 
    variant = 'primary', 
    onClick, 
    disabled = false,
    className = ''
}) {
    const variants = {
        primary: 'bg-blue-500 text-white hover:bg-blue-600',
        secondary: 'bg-gray-500 text-white hover:bg-gray-600',
        success: 'bg-green-500 text-white hover:bg-green-600',
        danger: 'bg-red-500 text-white hover:bg-red-600',
        warning: 'bg-yellow-500 text-white hover:bg-yellow-600'
    };

    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            className={`px-4 py-2 rounded-md font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 ${variants[variant]} ${className}`}
        >
            {children}
        </button>
    );
}

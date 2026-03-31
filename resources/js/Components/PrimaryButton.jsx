export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={`app-primary-btn ${disabled ? 'opacity-60' : ''} ${className}`}
            disabled={disabled}
        >
            {children}
        </button>
    );
}

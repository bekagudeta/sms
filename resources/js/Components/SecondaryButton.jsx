export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={`app-secondary-btn ${disabled ? 'opacity-60' : ''} ${className}`}
            disabled={disabled}
        >
            {children}
        </button>
    );
}

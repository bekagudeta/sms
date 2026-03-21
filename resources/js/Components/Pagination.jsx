import React from 'react';
import { Link } from '@inertiajs/react';

export default function Pagination({ links }) {
    if (!links || links.length === 0) {
        return null;
    }

    return (
        <nav className="mt-6 flex items-center justify-between" aria-label="Pagination">
            <ul className="inline-flex -space-x-px text-sm">
                {links.map((link, index) => {
                    const isDisabled = !link.url;
                    const isActive = link.active;
                    const label = link.label?.toString();

                    const baseClasses =
                        'px-3 py-1 border border-gray-200 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1';

                    const activeClasses =
                        'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700';

                    const disabledClasses =
                        'bg-white text-gray-300 border-gray-200 cursor-not-allowed';

                    const classes = `${baseClasses} ${isActive ? activeClasses : isDisabled ? disabledClasses : 'bg-white text-gray-700'}`;

                    // Laravel provides labels that may contain HTML entities (e.g., &laquo;)
                    const content = <span dangerouslySetInnerHTML={{ __html: label }} />;

                    return (
                        <li key={index}>
                            {isDisabled ? (
                                <span className={classes}>{content}</span>
                            ) : (
                                <Link
                                    href={link.url}
                                    className={classes}
                                    preserveScroll
                                >
                                    {content}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

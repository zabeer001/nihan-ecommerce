import React from 'react';

function isEmptyValue(value) {
    return value === null || value === undefined || value === '';
}

function CompactDetailSection({ title, children, className = '' }) {
    return (
        <div className={`rounded-box border border-base-300 bg-base-100 p-4 ${className}`.trim()}>
            {title ? <h2 className="mb-3 text-lg font-bold">{title}</h2> : null}
            {children}
        </div>
    );
}

function CompactDetailGrid({ children, className = '', columns = 'md:grid-cols-2' }) {
    return (
        <div className={`grid gap-x-6 gap-y-2 ${columns} ${className}`.trim()}>
            {children}
        </div>
    );
}

function CompactDetailRow({ label, title, value, className = '' }) {
    const content = isEmptyValue(value) ? '-' : value;

    return (
        <div className={`grid grid-cols-[minmax(8rem,0.42fr)_1fr] gap-3 py-1 text-sm md:grid-cols-[minmax(9rem,0.34fr)_1fr] ${className}`.trim()}>
            <div className="font-semibold text-base-content/80">{label || title}</div>
            <div className="min-w-0 break-words text-base-content/90">{content}</div>
        </div>
    );
}

function CompactTextBlock({ label, title, value, children, className = '' }) {
    const content = children ?? (isEmptyValue(value) ? '-' : value);

    return (
        <div className={`rounded-lg border border-base-300 bg-base-200/60 p-3 ${className}`.trim()}>
            <p className="text-sm font-semibold text-base-content/80">{label || title}</p>
            <div className="mt-1 whitespace-pre-wrap break-words text-sm text-base-content/90">{content}</div>
        </div>
    );
}

export { CompactDetailGrid, CompactDetailRow, CompactDetailSection, CompactTextBlock };

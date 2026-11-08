import React from 'react';

function sanitizeHtml(html = '') {
    if (!html) {
        return '';
    }

    const template = document.createElement('template');
    template.innerHTML = html;
    template.content.querySelectorAll('script, style, iframe, object, embed').forEach((node) => node.remove());
    template.content.querySelectorAll('*').forEach((node) => {
        [...node.attributes].forEach((attribute) => {
            if (attribute.name.startsWith('on') || attribute.name === 'style') {
                node.removeAttribute(attribute.name);
            }
        });
    });

    return template.innerHTML;
}

function RichTextViewer({ value }) {
    if (!value) {
        return <p className="mt-1 text-sm font-semibold">-</p>;
    }

    return (
        <div
            className="prose prose-sm mt-1 max-w-none font-semibold"
            dangerouslySetInnerHTML={{ __html: sanitizeHtml(value) }}
        />
    );
}

export default RichTextViewer;

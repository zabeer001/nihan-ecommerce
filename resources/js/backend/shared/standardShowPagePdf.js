import { drawStaticBankDetails, staticBankDetailsHeight } from './staticBankDetailsPdf';
import {
    documentSignatureFooterFits,
    drawDocumentSignatureFooter,
    PDF_COMPANY_NAME,
} from './pdfDocumentDefaults';

function cleanText(element) {
    return (element?.innerText || element?.textContent || '').trim().replace(/\s+/g, ' ');
}

function addPair(pairs, seen, label, value) {
    const cleanLabel = String(label || '').trim();
    const cleanValue = String(value || '').trim() || '-';
    const signature = `${cleanLabel.toLowerCase()}::${cleanValue}`;

    if (!cleanLabel || cleanLabel.length > 80 || seen.has(signature)) {
        return;
    }

    seen.add(signature);
    pairs.push([cleanLabel, cleanValue]);
}

function extractPairs(content) {
    const pairs = [];
    const seen = new Set();

    content.querySelectorAll('div[class*="grid-cols-[minmax"]').forEach((row) => {
        const children = [...row.children];
        if (children.length >= 2) addPair(pairs, seen, cleanText(children[0]), cleanText(children[1]));
    });

    content.querySelectorAll('.stat').forEach((stat) => {
        addPair(pairs, seen, cleanText(stat.querySelector('.stat-title')), cleanText(stat.querySelector('.stat-value')));
    });

    content.querySelectorAll('div.rounded-lg, div.rounded-box').forEach((block) => {
        if (block.querySelector('table') || block.matches('[class*="grid-cols-[minmax"]')) return;

        const children = [...block.children].filter((child) => !child.matches('svg, button'));
        if (children.length < 2) return;

        const label = cleanText(children[0]);
        const value = cleanText(children[1]);
        if (label && label.length <= 80 && value && value.length <= 1200) addPair(pairs, seen, label, value);
    });

    return pairs;
}

function extractTables(content) {
    return [...content.querySelectorAll('table')].map((table, index) => {
        const headerCells = [...table.querySelectorAll('thead th')].map(cleanText);
        const bodyRows = [...table.querySelectorAll('tbody tr')].map((row) => (
            [...row.querySelectorAll('th, td')].map(cleanText)
        ));
        const footerRows = [...table.querySelectorAll('tfoot tr')].map((row) => (
            [...row.querySelectorAll('th, td')].map(cleanText)
        ));
        let heading = '';
        let current = table.parentElement;

        while (current && current !== content && !heading) {
            const candidate = current.previousElementSibling || current.querySelector(':scope > p, :scope > h2, :scope > h3, :scope > h4');
            heading = cleanText(candidate);
            current = current.parentElement;
        }

        return {
            title: heading && heading.length <= 80 ? heading : `Table ${index + 1}`,
            head: headerCells.length ? [headerCells] : [],
            body: [...bodyRows, ...footerRows],
        };
    }).filter((table) => table.head.length || table.body.length);
}

function addPageNumbers(doc) {
    const totalPages = doc.getNumberOfPages();

    for (let page = 1; page <= totalPages; page += 1) {
        doc.setPage(page);
        doc.setDrawColor(209, 213, 219);
        doc.line(14, 283, 196, 283);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(107, 114, 128);
        doc.text(PDF_COMPANY_NAME, 14, 289);
        doc.text(`Page ${page} of ${totalPages}`, 196, 289, { align: 'right' });
    }
}

export default function generateStandardShowPagePdf(doc, autoTable, content, options = {}) {
    const title = cleanText(content.querySelector('h1')) || 'Document Details';
    const subtitle = cleanText(content.querySelector('h1')?.parentElement?.querySelector('p'));
    const pairs = extractPairs(content);
    const tables = extractTables(content);

    doc.setTextColor(17, 24, 39);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.text(PDF_COMPANY_NAME, 105, 16, { align: 'center' });
    doc.setFontSize(15);
    doc.text(title, 105, 25, { align: 'center' });
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    if (subtitle) doc.text(doc.splitTextToSize(subtitle, 150), 105, 32, { align: 'center' });
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('DETAIL REPORT', 105, 40, { align: 'center' });
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.text(new Date().toLocaleDateString(), 196, 40, { align: 'right' });
    let y = 48;
    if (pairs.length) {
        doc.setTextColor(17, 24, 39);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.text('DETAILS', 14, y);

        const rows = [];
        for (let index = 0; index < pairs.length; index += 2) {
            const first = pairs[index];
            const second = pairs[index + 1] || ['', ''];
            rows.push([first[0], first[1], second[0], second[1]]);
        }

        autoTable(doc, {
            startY: y + 3,
            theme: 'grid',
            margin: { left: 14, right: 14, bottom: 18 },
            body: rows,
            styles: { fontSize: 8, cellPadding: 2.2, lineColor: [209, 213, 219], lineWidth: 0.15, textColor: [17, 24, 39] },
            columnStyles: {
                0: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
                1: { cellWidth: 59 },
                2: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
                3: { cellWidth: 59 },
            },
        });
        y = doc.lastAutoTable.finalY + 5;
    }

    tables.forEach((table) => {
        if (y > 255) {
            doc.addPage();
            y = 18;
        }

        doc.setTextColor(17, 24, 39);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.text(table.title.toUpperCase(), 14, y);
        autoTable(doc, {
            startY: y + 3,
            theme: 'grid',
            margin: { left: 14, right: 14, bottom: 18 },
            head: table.head,
            body: table.body,
            styles: { fontSize: 7.2, cellPadding: 2, lineColor: [156, 163, 175], lineWidth: 0.15, textColor: [17, 24, 39], overflow: 'linebreak' },
            headStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [249, 250, 251] },
        });
        y = doc.lastAutoTable.finalY + 5;
    });

    if (!pairs.length && !tables.length) {
        doc.setTextColor(17, 24, 39);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.text('No detail data is available for this document.', 14, y);
    }

    if (!documentSignatureFooterFits(y + staticBankDetailsHeight(doc) + 8, options.signatureImage)) {
        doc.addPage();
        y = 18;
    }

    y = drawStaticBankDetails(doc, y) + 8;
    drawDocumentSignatureFooter(doc, y, options.signatureImage);
    addPageNumbers(doc);
}

import { drawStaticBankDetails, staticBankDetailsHeight } from './staticBankDetailsPdf';
import {
    documentSignatureFooterFits,
    drawDocumentHeader,
    drawDocumentSignatureFooter,
    pdfPlainText,
} from './pdfDocumentDefaults';
import { drawCustomerAddressBoxes } from './pdfCustomerAddress';
import { calculateOrdersTotalWeight } from './proformaInvoiceWeight';
import { formatAmount, formatInteger, roundGlobalNumber } from '@/shared/numberFormat';

function value(value, fallback = '-') {
    return value === null || value === undefined || value === '' ? fallback : String(value);
}

function number(valueToFormat, decimals = 2) {
    return decimals === 0 ? formatInteger(valueToFormat) : formatAmount(valueToFormat);
}

function orderItems(proformaInvoice) {
    const orders = Array.isArray(proformaInvoice.orders) && proformaInvoice.orders.length
        ? proformaInvoice.orders
        : [proformaInvoice.order].filter(Boolean);

    return orders.flatMap((order) => (
        (Array.isArray(order.items) ? order.items : []).flatMap((item) => {
            const variants = Array.isArray(item.styles) ? item.styles : [];

            if (!variants.length) {
                return [{ order, item, variant: null, variantIndex: null }];
            }

            return variants.map((variant, variantIndex) => ({
                order,
                item,
                variant,
                variantIndex,
            }));
        })
    ));
}

function baseOrderItems(proformaInvoice) {
    const orders = Array.isArray(proformaInvoice.orders) && proformaInvoice.orders.length
        ? proformaInvoice.orders
        : [proformaInvoice.order].filter(Boolean);

    return orders.flatMap((order) => (
        (Array.isArray(order.items) ? order.items : []).map((item) => ({
            order,
            item,
            variant: null,
            variantIndex: null,
        }))
    ));
}

function variantLabel(variant, variantIndex) {
    if (!variant) {
        return '-';
    }

    return [
        `Variant #${variantIndex + 1}`,
        variant.style_name,
        variant.style_code,
    ].filter(Boolean).join('\n');
}

function variantDetails(variant) {
    if (!variant) {
        return '-';
    }

    const details = [
        variant.color ? `Color: ${variant.color}` : '',
        variant.size ? `Size: ${variant.size}` : '',
        ...Object.entries(variant.custom_fields || {})
            .filter(([, fieldValue]) => fieldValue !== null && fieldValue !== undefined && fieldValue !== '')
            .map(([key, fieldValue]) => `${key}: ${fieldValue}`),
        variant.remarks ? `Remarks: ${variant.remarks}` : '',
    ].filter(Boolean);

    return details.length ? details.join('\n') : '-';
}

function addPageNumbers(doc, fontScale = 1) {
    const pages = doc.getNumberOfPages();

    for (let page = 1; page <= pages; page += 1) {
        doc.setPage(page);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9 * fontScale);
        doc.setTextColor(0, 0, 0);
        doc.text(`Page ${page} of ${pages}`, 196, 289, { align: 'right' });
    }
}

function secondaryDetailsHeight(doc, details, width = 88, fontScale = 1) {
    if (!details?.lines?.length) {
        return 10;
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9 * fontScale);

    return 10 + details.lines.reduce((height, line) => (
        height + (doc.splitTextToSize(value(line), width).length * 4 * fontScale)
    ), 0);
}

function drawSecondaryDetails(doc, y, details, options = {}) {
    const x = options.x ?? 108;
    const width = options.width ?? 88;

    doc.setFont('helvetica', 'bold');
    const fontScale = options.fontScale ?? 1;
    doc.setFontSize(10 * fontScale);
    doc.text(value(details?.title, 'Details'), x, y);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9 * fontScale);

    let currentY = y + 6;
    (details?.lines || []).forEach((line) => {
        const wrappedLine = doc.splitTextToSize(value(line), width);
        doc.text(wrappedLine, x, currentY);
        currentY += wrappedLine.length * 4 * fontScale;
    });

    return currentY + 4;
}

export default function generateProformaInvoicePdf(doc, autoTable, proformaInvoice, options = {}) {
    const orders = Array.isArray(proformaInvoice.orders) && proformaInvoice.orders.length
        ? proformaInvoice.orders
        : [proformaInvoice.order].filter(Boolean);
    const primaryOrder = orders.find((order) => Number(order.id) === Number(proformaInvoice.order_id)) || orders[0] || {};
    const customer = primaryOrder.customer || proformaInvoice.customer || {};
    const includeVariations = options.includeVariations === true;
    const useOrderTableColumns = options.useOrderTableColumns === true;
    const items = includeVariations ? orderItems(proformaInvoice) : baseOrderItems(proformaInvoice);
    const densityUnits = items.reduce((total, row) => {
        const itemText = [row.item?.item_name, row.item?.description, variantDetails(row.variant)]
            .filter(Boolean)
            .join(' ');

        return total + Math.max(1, Math.ceil(itemText.length / 55));
    }, 0);
    const fontScale = options.forceSinglePage === false || densityUnits <= 6
        ? 1
        : 6 / densityUnits;
    const currency = value(proformaInvoice.currency, 'BDT');
    const totalQuantity = items.reduce((total, row) => total + Number(row.variant?.quantity ?? row.item.quantity ?? 0), 0);
    const totalWeight = calculateOrdersTotalWeight(orders);
    const totalWeightKg = totalWeight / 1000;
    const calculatedGrandTotal = items.reduce((total, row) => total + Number(row.variant?.line_total ?? row.item.line_total ?? 0), 0);
    const hasSavedGrandTotal = proformaInvoice.grand_total !== null
        && proformaInvoice.grand_total !== undefined
        && proformaInvoice.grand_total !== '';
    const grandTotal = roundGlobalNumber(hasSavedGrandTotal ? proformaInvoice.grand_total : calculatedGrandTotal);
    const defaultMetadataRows = [
        ['Issue Date', value(proformaInvoice.pi_date), 'Shipment Method', value(proformaInvoice.shipment_method)],
        ['Delivery Date', value(proformaInvoice.delivery_date || primaryOrder.delivery_date), 'Shipping Terms', value(proformaInvoice.shipping_terms)],
        ['PI Expire Date', value(proformaInvoice.pi_expire_date), 'Country of Origin', value(proformaInvoice.country_of_origin)],
        ...(options.showBrandCoordinator === false ? [] : [
            ['Brand', value(proformaInvoice.brand), 'Coordinator', value(proformaInvoice.coordinator)],
        ]),
    ];
    const metadataRows = options.metadataRowsOnly
        ? (options.metadataRows || [])
        : [...defaultMetadataRows, ...(options.metadataRows || [])];

    drawDocumentHeader(doc, {
        documentTitle: options.documentTitle || 'PROFORMA INVOICE',
        documentNumberLabel: options.documentNumberLabel || 'PI No',
        documentNumber: value(proformaInvoice.pi_number),
        signatureImage: options.signatureImage,
        emphasized: true,
        fontScale,
    });

    const addressBottom = drawCustomerAddressBoxes(doc, customer, 39, {
        fontSize: 9 * fontScale,
        headerFontSize: 10 * fontScale,
        lineHeight: 3.8 * fontScale,
        boldDetails: true,
        black: true,
    });

    autoTable(doc, {
        startY: addressBottom + 3,
        theme: 'plain',
        margin: { left: 14, right: 14 },
        tableWidth: 182,
        body: metadataRows,
        styles: { font: 'helvetica', fontSize: 9 * fontScale, fontStyle: 'bold', cellPadding: 1.5 * fontScale, textColor: [0, 0, 0] },
        columnStyles: { 0: { fontStyle: 'bold' }, 2: { fontStyle: 'bold' } },
    });

    if (options.showTotalsSummary !== false) {
        autoTable(doc, {
            startY: doc.lastAutoTable.finalY + 3,
            theme: 'grid',
            margin: { left: 14, right: 14 },
            head: [['Total Weight', 'Total Order Qty', `Grand Total (${currency})`]],
            body: [[`${number(totalWeightKg)} kg`, number(totalQuantity, 0), number(grandTotal)]],
            styles: { halign: 'center', fontSize: 9 * fontScale, fontStyle: 'bold', cellPadding: 2.2 * fontScale, lineColor: [0, 0, 0], lineWidth: 0.2, textColor: [0, 0, 0] },
            headStyles: { fillColor: [243, 244, 246], textColor: [0, 0, 0], fontStyle: 'bold' },
        });
    }

    autoTable(doc, {
        startY: doc.lastAutoTable.finalY + 4,
        theme: 'grid',
        margin: { left: 14, right: 14 },
        head: [useOrderTableColumns
            ? ['Sl', 'Order No', 'PO No', 'Reference', 'Item', 'Variant Details', 'UoM', 'Qty']
            : includeVariations
            ? ['Sl', 'Order No', 'PO No', 'Reference', 'Item', 'Variant', 'Variant Details', 'HS Code', 'Unit Weight\n(gm)', 'UoM', 'Qty', `Unit Price\n(${currency})`, `Amount\n(${currency})`]
            : ['Sl', 'Order No', 'PO No', 'Reference', 'Item', 'HS Code', 'Unit Weight\n(gm)', 'UoM', 'Qty', `Unit Price\n(${currency})`, `Amount\n(${currency})`]],
        body: items.length ? items.map(({ order, item, variant, variantIndex }, index) => (
            useOrderTableColumns ? [
                index + 1,
                value(order.order_number),
                value(order.po_number),
                value(order.reference_number),
                options.compactOrderItems
                    ? [item.item_code || item.item_name, item.item_type ? `Type: ${item.item_type}` : ''].filter(Boolean).join(' | ')
                    : [value(item.item_name), item.description].filter(Boolean).join('\n'),
                variantDetails(variant),
                value(item.unit),
                number(variant?.quantity ?? item.quantity, 0),
            ] : [
                index + 1,
                value(order.order_number),
                value(order.po_number),
                value(order.reference_number),
                [value(item.item_name), item.description].filter(Boolean).join('\n'),
                ...(includeVariations ? [variantLabel(variant, variantIndex), variantDetails(variant)] : []),
                value(item.hh_code),
                variantIndex === null || variantIndex === 0 ? number(item.weight) : '',
                value(item.unit),
                number(variant?.quantity ?? item.quantity, 0),
                number(variant?.unit_price ?? item.unit_price, 4),
                number(variant?.line_total ?? item.line_total),
            ]
        )) : [useOrderTableColumns
            ? ['', '', '', '', 'No order items found', '', '', '']
            : includeVariations
            ? ['', '', '', '', 'No order items found', '', '', '', '', '', '', '', '']
            : ['', '', '', '', 'No order items found', '', '', '', '', '', '']],
        foot: [useOrderTableColumns
            ? ['', '', '', '', '', '', 'Total Qty', number(totalQuantity, 0)]
            : includeVariations
            ? ['', '', '', '', '', '', '', '', `${number(totalWeightKg)} kg`, '', number(totalQuantity, 0), 'Grand Total', number(grandTotal)]
            : ['', '', '', '', '', '', `${number(totalWeightKg)} kg`, '', number(totalQuantity, 0), 'Grand Total', number(grandTotal)]],
        styles: { fontSize: (options.itemTableFontSize ?? 7.5) * fontScale, fontStyle: 'bold', cellPadding: 1.5 * fontScale, lineColor: [0, 0, 0], lineWidth: 0.15, overflow: 'linebreak', textColor: [0, 0, 0] },
        headStyles: { fillColor: [243, 244, 246], textColor: [0, 0, 0], fontStyle: 'bold' },
        footStyles: { fillColor: [243, 244, 246], textColor: [0, 0, 0], fontStyle: 'bold' },
        columnStyles: useOrderTableColumns ? {
            0: { cellWidth: 9 },
            1: { cellWidth: 22 },
            2: { cellWidth: 20 },
            3: { cellWidth: 22 },
            4: { cellWidth: 42 },
            5: { cellWidth: 43 },
            6: { cellWidth: 11 },
            7: { cellWidth: 13, halign: 'right' },
        } : includeVariations ? {
            0: { cellWidth: 7 },
            1: { cellWidth: 15 },
            2: { cellWidth: 12 },
            3: { cellWidth: 14 },
            4: { cellWidth: 16 },
            5: { cellWidth: 15 },
            6: { cellWidth: 30 },
            7: { cellWidth: 12 },
            8: { cellWidth: 10, halign: 'right' },
            9: { cellWidth: 8 },
            10: { cellWidth: 11, halign: 'right' },
            11: { cellWidth: 15, halign: 'right' },
            12: { cellWidth: 17, halign: 'right' },
        } : options.itemTableColumnStyles || {
            0: { cellWidth: 7 },
            1: { cellWidth: 17 },
            2: { cellWidth: 14 },
            3: { cellWidth: 15 },
            4: { cellWidth: 44 },
            5: { cellWidth: 14 },
            6: { cellWidth: 12, halign: 'right' },
            7: { cellWidth: 10 },
            8: { cellWidth: 13, halign: 'right' },
            9: { cellWidth: 17, halign: 'right' },
            10: { cellWidth: 19, halign: 'right' },
        },
    });

    if (options.showAmountInWords !== false) {
        autoTable(doc, {
            startY: doc.lastAutoTable.finalY + 3,
            theme: 'grid',
            margin: { left: 14, right: 14 },
            body: [[
                'Amount in Words',
                value(pdfPlainText(proformaInvoice.amount_in_words)),
            ]],
            styles: {
                fontSize: 9 * fontScale,
                fontStyle: 'bold',
                cellPadding: 2 * fontScale,
                lineColor: [0, 0, 0],
                lineWidth: 0.15,
                overflow: 'linebreak',
                textColor: [0, 0, 0],
            },
            columnStyles: {
                0: { cellWidth: 34, fontStyle: 'bold', fillColor: [243, 244, 246] },
                1: { cellWidth: 148 },
            },
        });
    }

    let y = doc.lastAutoTable.finalY + 5;
    const showPaymentTerms = options.showPaymentTerms !== false;
    const terms = [
        pdfPlainText(customer.payment_terms) || '-',
        customer.notes ? `Remarks: ${pdfPlainText(customer.notes)}` : '',
    ].filter(Boolean);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9 * fontScale);
    const wrappedTerms = doc.splitTextToSize(terms.join('\n'), 84);
    const termsHeight = showPaymentTerms ? 10 + (wrappedTerms.length * 4 * fontScale) : 0;
    const secondaryX = showPaymentTerms ? 108 : 14;
    const secondaryWidth = showPaymentTerms ? 88 : 182;
    const secondaryHeight = options.secondaryDetails
        ? secondaryDetailsHeight(doc, options.secondaryDetails, secondaryWidth, fontScale)
        : staticBankDetailsHeight(doc, 88, { fontSize: 8.5 * fontScale, lineHeight: 4.2 * fontScale });

    const detailsHeight = Math.max(termsHeight, secondaryHeight);
    if (!documentSignatureFooterFits(y + detailsHeight + 6, options.signatureImage)) {
        doc.addPage();
        y = 18;
    }

    if (showPaymentTerms) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10 * fontScale);
        doc.text('Payment Terms', 14, y);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9 * fontScale);
        doc.text(wrappedTerms, 14, y + 6);
    }
    const termsBottom = showPaymentTerms ? y + termsHeight : y;
    const secondaryBottom = options.secondaryDetails
        ? drawSecondaryDetails(doc, y, options.secondaryDetails, { x: secondaryX, width: secondaryWidth, fontScale })
        : drawStaticBankDetails(doc, y, {
            x: 108,
            width: 88,
            fontSize: 8.5 * fontScale,
            headingFontSize: 10 * fontScale,
            lineHeight: 4.2 * fontScale,
            boldValues: true,
            black: true,
        });
    const detailsBottom = Math.max(termsBottom, secondaryBottom);

    drawDocumentSignatureFooter(doc, detailsBottom + 6, options.signatureImage, {
        left: 'Created by',
        right: 'Authorized by',
        emphasized: true,
        fontSize: 10 * fontScale,
    });

    addPageNumbers(doc, fontScale);
}

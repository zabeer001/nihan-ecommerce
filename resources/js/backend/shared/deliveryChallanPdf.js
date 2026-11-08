import {
    documentSignatureFooterFits,
    drawDocumentHeader,
    drawDocumentSignatureFooter,
} from './pdfDocumentDefaults';
import { drawCustomerAddressBoxes } from './pdfCustomerAddress';
import { formatAmount, formatInteger } from '@/shared/numberFormat';

function value(rawValue, fallback = '-') {
    return rawValue === null || rawValue === undefined || rawValue === '' ? fallback : String(rawValue);
}

function number(rawValue, decimals = 2) {
    return decimals === 0 ? formatInteger(rawValue) : formatAmount(rawValue);
}

function titleize(rawValue) {
    return value(rawValue).replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

function customFieldsText(customFields, fieldOrder = []) {
    if (!customFields || typeof customFields !== 'object' || Array.isArray(customFields)) {
        return '-';
    }

    const orderedKeys = [
        ...fieldOrder.filter((key) => Object.hasOwn(customFields, key)),
        ...Object.keys(customFields).filter((key) => !fieldOrder.includes(key)),
    ];
    const fields = orderedKeys.map((key) => [key, customFields[key]])
        .filter(([, fieldValue]) => fieldValue !== null && fieldValue !== undefined && fieldValue !== '')
        .map(([key, fieldValue]) => `${key}: ${fieldValue}`);

    return fields.length ? fields.join('\n') : '-';
}

function addSectionTitle(doc, title, y) {
    doc.setTextColor(17, 24, 39);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text(title, 14, y);
}

const FOOTER_REMARKS = "Immediately upon receipt of goods, the Buyer is responsible for inspecting the contents of all shipments and deliveries to assure that specifications have been met prior to incorporating R-Locket products into the buyer's products. If R-Locket products fail to meet specifications, the buyer may return the non-conforming products for replacement or credit within 5 days of receipt. In no event will R-Locket be responsible for damages or consequential damages other than credit or replacement. If there is any problem, please feel free to contact R-Locket Customer Service.";

function footerRemarksHeight(doc) {
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);

    return 10 + (doc.splitTextToSize(FOOTER_REMARKS, 182).length * 3.5);
}

function drawFooterRemarks(doc, y) {

    addSectionTitle(doc, 'REMARKS', y);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(17, 24, 39);
    const wrappedRemarks = doc.splitTextToSize(FOOTER_REMARKS, 182);
    doc.text(wrappedRemarks, 14, y + 6);

    return y + 10 + (wrappedRemarks.length * 3.5);
}

function addPageNumbers(doc) {
    const pages = doc.getNumberOfPages();

    for (let page = 1; page <= pages; page += 1) {
        doc.setPage(page);
        doc.setDrawColor(209, 213, 219);
        doc.line(14, 283, 196, 283);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(107, 114, 128);
        doc.text('Delivery Challan', 14, 289);
        doc.text(`Page ${page} of ${pages}`, 196, 289, { align: 'right' });
    }
}

function isEarlierChallan(entry, currentChallan) {
    if (!currentChallan?.id) {
        return true;
    }

    const entryDate = String(entry.challan_date || '');
    const currentDate = String(currentChallan.challan_date || '');

    if (entryDate && currentDate && entryDate !== currentDate) {
        return entryDate < currentDate;
    }

    return Number(entry.challan_id) < Number(currentChallan.id);
}

function previousDelivered(variant, currentChallan) {
    return (variant.history || [])
        .filter((entry) => entry.status === 'delivered' && isEarlierChallan(entry, currentChallan))
        .reduce((total, entry) => total + Number(entry.quantity || 0), 0);
}

function currentLineMap(challan) {
    return new Map(
        (Array.isArray(challan.variant_lines) ? challan.variant_lines : [])
            .filter((line) => line.order_item_style_id && !line.legacy_unallocated)
            .map((line) => [Number(line.order_item_style_id), line]),
    );
}

function variantRows(challan) {
    const inventory = challan.variant_inventory || {};
    const variants = Array.isArray(inventory.variants) ? inventory.variants : [];
    const lineMap = currentLineMap(challan);

    return variants.map((variant, index) => {
        const line = lineMap.get(Number(variant.order_item_style_id));
        const itemName = line?.item_name_snapshot || variant.item_name || variant.item_code;
        const itemCode = line?.item_code_snapshot || variant.item_code;
        const previouslySent = previousDelivered(variant, challan);
        const currentQuantity = Number(line?.quantity || 0);
        const remainingBefore = Math.max(Number(variant.ordered_qty || 0) - previouslySent, 0);
        const remainingAfter = challan.status === 'delivered'
            ? Math.max(remainingBefore - currentQuantity, 0)
            : remainingBefore;

        return [
            index + 1,
            itemCode && itemCode !== itemName ? `${value(itemName)}\n${itemCode}` : value(itemName),
            customFieldsText(line?.custom_fields_snapshot || variant.custom_fields, variant.custom_fields_order),
            `${number(variant.ordered_qty)} ${value(variant.unit, '')}`.trim(),
            `${number(previouslySent)} ${value(variant.unit, '')}`.trim(),
            `${number(currentQuantity)} ${value(line?.unit || variant.unit, '')}`.trim(),
            `${number(remainingAfter)} ${value(variant.unit, '')}`.trim(),
        ];
    });
}

function variantTotals(challan) {
    const inventory = challan.variant_inventory || {};
    const variants = Array.isArray(inventory.variants) ? inventory.variants : [];
    const lineMap = currentLineMap(challan);
    const totals = variants.reduce((result, variant) => {
        const line = lineMap.get(Number(variant.order_item_style_id));
        const ordered = Number(variant.ordered_qty || 0);
        const previouslySent = previousDelivered(variant, challan);
        const currentQuantity = Number(line?.quantity || 0);
        const remainingBefore = Math.max(ordered - previouslySent, 0);
        const remainingAfter = challan.status === 'delivered'
            ? Math.max(remainingBefore - currentQuantity, 0)
            : remainingBefore;

        return {
            ordered: result.ordered + ordered,
            previouslySent: result.previouslySent + previouslySent,
            currentQuantity: result.currentQuantity + currentQuantity,
            remaining: result.remaining + remainingAfter,
        };
    }, {
        ordered: 0,
        previouslySent: 0,
        currentQuantity: 0,
        remaining: 0,
    });
    const unit = value(variants[0]?.unit || challan.delivery_unit, 'PCS');
    const withUnit = (quantity) => `${number(quantity)} ${unit}`;

    return [
        '',
        '',
        'Total',
        withUnit(totals.ordered),
        withUnit(totals.previouslySent),
        withUnit(totals.currentQuantity),
        withUnit(totals.remaining),
    ];
}

function previousChallanRows(challan) {
    const inventory = challan.variant_inventory || {};
    const variants = Array.isArray(inventory.variants) ? inventory.variants : [];
    const legacy = Array.isArray(inventory.legacy_unallocated) ? inventory.legacy_unallocated : [];
    const entries = [
        ...variants.flatMap((variant) => (
            (variant.history || []).map((entry) => ({ ...entry, unit: entry.unit || variant.unit }))
        )),
        ...legacy,
    ].filter((entry) => {
        if (entry.status !== 'delivered') {
            return false;
        }

        return isEarlierChallan(entry, challan);
    });
    const challans = new Map();

    entries.forEach((entry) => {
        const challanId = Number(entry.challan_id);
        const existing = challans.get(challanId) || {
            id: challanId,
            number: entry.challan_number,
            quantities: new Map(),
        };
        const unit = value(entry.unit, '');
        existing.quantities.set(unit, (existing.quantities.get(unit) || 0) + Number(entry.quantity || 0));
        challans.set(challanId, existing);
    });

    return [...challans.values()]
        .sort((first, second) => second.id - first.id)
        .map((entry) => [
            value(entry.number, `Challan #${entry.id}`),
            [...entry.quantities.entries()]
                .map(([unit, quantity]) => `${number(quantity)} ${unit}`.trim())
                .join(', '),
        ]);
}

export default function generateDeliveryChallanPdf(doc, autoTable, challan, options = {}) {
    const customer = challan.customer || {};
    const order = challan.order || {};
    const proformaInvoice = challan.proforma_invoice || {};

    drawDocumentHeader(doc, {
        documentTitle: 'DELIVERY CHALLAN',
        documentNumberLabel: 'Challan No',
        documentNumber: value(challan.challan_number),
        signatureImage: options.signatureImage,
    });
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.text(`Date: ${value(challan.challan_date)}`, 14, 40);
    doc.text(`Status: ${titleize(challan.status)}`, 196, 40, { align: 'right' });

    const addressBottom = drawCustomerAddressBoxes(doc, customer, 44);

    addSectionTitle(doc, 'CHALLAN DETAILS', addressBottom + 5);
    autoTable(doc, {
        startY: addressBottom + 8,
        theme: 'grid',
        margin: { left: 14, right: 14, bottom: 18 },
        body: [
            ['Customer', value(customer.name), 'Order Number', value(order.order_number)],
            ['PI Number', value(proformaInvoice.pi_number), 'PO Number', value(order.po_number)],
            ['Delivery Quantity', `${number(challan.delivery_qty)} ${value(challan.delivery_unit, '')}`.trim(), 'Delivery Person', value(challan.delivery_person_name)],
            ['Gross Weight', `${number(challan.gross_weight)} kg`, 'Net Weight', `${number(challan.net_weight)} kg`],
        ],
        styles: { fontSize: 8, cellPadding: 2.2, lineColor: [209, 213, 219], lineWidth: 0.15, textColor: [17, 24, 39] },
        columnStyles: {
            0: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
            1: { cellWidth: 59 },
            2: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
            3: { cellWidth: 59 },
        },
    });

    let y = doc.lastAutoTable.finalY + 5;
    const variants = variantRows(challan);
    if (variants.length) {
        y = doc.lastAutoTable.finalY + 5;
        if (y > 250) {
            doc.addPage();
            y = 18;
        }

        addSectionTitle(doc, 'DELIVERY DETAILS', y);
        autoTable(doc, {
            startY: y + 3,
            theme: 'grid',
            margin: { left: 14, right: 14, bottom: 18 },
            head: [['No.', 'Item', 'Custom Fields', 'Ordered', 'Previously Sent', 'This Challan', 'Remaining']],
            body: variants,
            foot: [variantTotals(challan)],
            styles: { fontSize: 6.7, cellPadding: 1.7, lineColor: [156, 163, 175], lineWidth: 0.15, textColor: [17, 24, 39], overflow: 'linebreak' },
            headStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
            footStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [249, 250, 251] },
            columnStyles: {
                0: { cellWidth: 8, halign: 'center' },
                1: { cellWidth: 31 },
                2: { cellWidth: 43 },
                3: { cellWidth: 24, halign: 'right' },
                4: { cellWidth: 26, halign: 'right' },
                5: { cellWidth: 25, halign: 'right' },
                6: { cellWidth: 25, halign: 'right' },
            },
        });
    }

    const previousChallans = previousChallanRows(challan);
    if (previousChallans.length) {
        y = doc.lastAutoTable.finalY + 5;
        if (y > 250) {
            doc.addPage();
            y = 18;
        }

        addSectionTitle(doc, 'PREVIOUS CHALLANS', y);
        autoTable(doc, {
            startY: y + 3,
            theme: 'grid',
            margin: { left: 14, right: 14, bottom: 18 },
            head: [['Challan Number', 'Delivered Quantity']],
            body: previousChallans,
            styles: { fontSize: 7, cellPadding: 1.8, lineColor: [156, 163, 175], lineWidth: 0.15, textColor: [17, 24, 39], overflow: 'linebreak' },
            headStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [249, 250, 251] },
            columnStyles: {
                0: { cellWidth: 91 },
                1: { cellWidth: 91, halign: 'right' },
            },
        });
    }

    const legacyLines = (Array.isArray(challan.variant_lines) ? challan.variant_lines : [])
        .filter((line) => line.legacy_unallocated);
    if (legacyLines.length) {
        y = doc.lastAutoTable.finalY + 5;
        if (y > 250) {
            doc.addPage();
            y = 18;
        }

        addSectionTitle(doc, 'LEGACY UNALLOCATED QUANTITIES', y);
        autoTable(doc, {
            startY: y + 3,
            theme: 'grid',
            margin: { left: 14, right: 14, bottom: 18 },
            head: [['Item', 'Variant Snapshot', 'Custom Fields', 'Quantity']],
            body: legacyLines.map((line) => [
                value(line.item_name_snapshot || line.item_code_snapshot),
                value(line.variant_name_snapshot, 'Legacy unallocated'),
                customFieldsText(line.custom_fields_snapshot),
                `${number(line.quantity)} ${value(line.unit, '')}`.trim(),
            ]),
            styles: { fontSize: 7, cellPadding: 2, lineColor: [156, 163, 175], lineWidth: 0.15, textColor: [17, 24, 39], overflow: 'linebreak' },
            headStyles: { fillColor: [243, 244, 246], textColor: [17, 24, 39], fontStyle: 'bold' },
        });
    }

    let contentBottom = doc.lastAutoTable?.finalY ? doc.lastAutoTable.finalY + 5 : y;

    if (!documentSignatureFooterFits(contentBottom + footerRemarksHeight(doc) + 4, options.signatureImage)) {
        doc.addPage();
        contentBottom = 18;
    }

    const remarksBottom = drawFooterRemarks(doc, contentBottom);
    drawDocumentSignatureFooter(doc, remarksBottom + 4, options.signatureImage, {
        left: 'Created by',
        right: 'Goods received by',
    });
    addPageNumbers(doc);
}

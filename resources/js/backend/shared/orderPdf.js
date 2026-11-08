import generateProformaInvoicePdf from './proformaInvoicePdf';

function itemDetailLines(items) {
    if (!items.length) {
        return ['No order item details found.'];
    }

    return items.map((item, index) => {
        const details = [
            item.item_code || item.item_name || `Item ${index + 1}`,
            item.item_code && item.item_name ? item.item_name : '',
            item.item_type ? `Type: ${item.item_type}` : '',
            item.size_type ? `Size: ${item.size_type}` : '',
        ].filter(Boolean);

        return `${index + 1}. ${details.join(' | ')}`;
    });
}

export default function generateOrderPdf(doc, autoTable, payload, options = {}) {
    const order = payload.order || payload.sections?.order_details || {};
    const items = Array.isArray(payload.sections?.order_items)
        ? payload.sections.order_items
        : (Array.isArray(order.items) ? order.items : []);
    const customer = order.customer || {};
    const completeOrder = { ...order, items };
    const orderDocument = {
        order: completeOrder,
        orders: [completeOrder],
        customer,
        company_setting: order.company_setting || payload.company_setting || {},
        currency: order.currency || 'BDT',
        pi_number: order.order_number,
        pi_date: order.order_date,
        delivery_date: order.delivery_date,
        grand_total: order.material_amount,
    };

    generateProformaInvoicePdf(doc, autoTable, orderDocument, {
        ...options,
        documentTitle: 'ORDER',
        documentNumberLabel: 'Order No',
        includeVariations: true,
        useOrderTableColumns: true,
        compactOrderItems: true,
        showAmountInWords: false,
        showTotalsSummary: false,
        showPaymentTerms: false,
        metadataRowsOnly: true,
        secondaryDetails: {
            title: 'Item Details',
            lines: itemDetailLines(items),
        },
        metadataRows: [
            ['Order Date', order.order_date || '-', 'Delivery Date', order.delivery_date || '-'],
            ['PO Number', order.po_number || '-', 'Work Order Number', order.work_order_number || '-'],
            ['Reference', order.reference_number || '-', 'File Reference', order.file_reference || '-'],
        ],
    });
}

import generateProformaInvoicePdf from './proformaInvoicePdf';

function firstPresent(...values) {
    return values.find((candidate) => candidate !== null && candidate !== undefined && candidate !== '');
}

export default function generateInvoicePdf(doc, autoTable, invoice, options = {}) {
    const proformaInvoice = invoice.proforma_invoice || {};
    const invoiceOrders = Array.isArray(invoice.orders) ? invoice.orders : [];
    const piOrders = Array.isArray(proformaInvoice.orders) ? proformaInvoice.orders : [];
    const orders = invoiceOrders.some((order) => Array.isArray(order.items) && order.items.length)
        ? invoiceOrders
        : piOrders;

    const document = {
        ...proformaInvoice,
        company_setting: invoice.company_setting || proformaInvoice.company_setting,
        customer: invoice.customer || proformaInvoice.customer,
        orders,
        order: orders[0] || proformaInvoice.order,
        order_id: orders[0]?.id || proformaInvoice.order_id,
        pi_number: invoice.invoice_number,
        pi_date: invoice.invoice_date,
        currency: firstPresent(invoice.currency, proformaInvoice.currency, 'BDT'),
        payment_type: firstPresent(invoice.payment_type, proformaInvoice.payment_type),
        conversion_rate: firstPresent(invoice.conversion_rate, proformaInvoice.conversion_rate),
        subtotal: firstPresent(invoice.subtotal, invoice.total_amount, proformaInvoice.subtotal),
        grand_total: firstPresent(invoice.total_amount, invoice.payable_amount, proformaInvoice.grand_total),
        amount_in_words: firstPresent(invoice.amount_in_words, proformaInvoice.amount_in_words),
    };

    generateProformaInvoicePdf(doc, autoTable, document, {
        documentTitle: 'INVOICE',
        documentNumberLabel: 'Invoice No',
        showBrandCoordinator: false,
        metadataRows: [
            ['LC No', firstPresent(invoice.lc_no, '-'), 'LC Expiry Date', firstPresent(invoice.lc_expiry_date, '-')],
        ],
        signatureImage: options.signatureImage,
        includeVariations: false,
        itemTableFontSize: 7,
        forceSinglePage: false,
        itemTableColumnStyles: {
            0: { cellWidth: 7 },
            1: { cellWidth: 20 },
            2: { cellWidth: 17 },
            3: { cellWidth: 18 },
            4: { cellWidth: 28 },
            5: { cellWidth: 16 },
            6: { cellWidth: 14, halign: 'right' },
            7: { cellWidth: 10 },
            8: { cellWidth: 14, halign: 'right' },
            9: { cellWidth: 18, halign: 'right' },
            10: { cellWidth: 20, halign: 'right' },
        },
    });
}

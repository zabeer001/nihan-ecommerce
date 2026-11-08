import {
    PDF_COMPANY_NAME,
    documentSignatureFooterFits,
    drawDocumentHeader,
    drawDocumentSignatureFooter,
    pdfPlainText,
} from './pdfDocumentDefaults';
import { drawCustomerAddressBoxes } from './pdfCustomerAddress';
import { drawStaticBankDetails, staticBankDetailsHeight } from './staticBankDetailsPdf';
import { formatAmount } from '@/shared/numberFormat';

function value(rawValue, fallback = '-') {
    return rawValue === null || rawValue === undefined || rawValue === '' ? fallback : String(rawValue);
}

function number(rawValue) {
    return formatAmount(rawValue);
}

function titleize(rawValue) {
    return value(rawValue).replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
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
        doc.text(PDF_COMPANY_NAME, 14, 289);
        doc.text(`Page ${page} of ${pages}`, 196, 289, { align: 'right' });
    }
}

export default function generatePaymentPdf(doc, autoTable, payment, options = {}) {
    const invoice = payment.invoice || {};
    const proformaInvoice = payment.proforma_invoice || {};
    const customer = invoice.customer || proformaInvoice.customer || {};
    const currency = invoice.currency || proformaInvoice.currency || 'BDT';

    drawDocumentHeader(doc, {
        documentTitle: 'PAYMENT RECEIPT',
        documentNumberLabel: 'Reference',
        documentNumber: value(payment.reference_number, `Payment #${payment.id}`),
        signatureImage: options.signatureImage,
    });

    const addressBottom = drawCustomerAddressBoxes(doc, customer, 39);

    autoTable(doc, {
        startY: addressBottom + 3,
        theme: 'grid',
        margin: { left: 14, right: 14, bottom: 18 },
        body: [
            ['Customer', value(customer.name), 'Payment Date', value(payment.payment_date)],
            ['Invoice Number', value(invoice.invoice_number), 'PI Number', value(proformaInvoice.pi_number)],
            ['Payment Method', titleize(payment.payment_method), 'Currency', currency],
            ['Paid Amount', `${currency} ${number(payment.paid_amount)}`, 'Created By', value(payment.creator?.name)],
            ['Amount in Words', value(payment.amount_in_words), 'Payment Note', value(payment.payment_note)],
        ],
        styles: { fontSize: 8, cellPadding: 2.2, lineColor: [156, 163, 175], lineWidth: 0.15, textColor: [17, 24, 39], overflow: 'linebreak' },
        columnStyles: {
            0: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
            1: { cellWidth: 59 },
            2: { cellWidth: 32, fontStyle: 'bold', fillColor: [243, 244, 246] },
            3: { cellWidth: 59 },
        },
    });

    let y = doc.lastAutoTable.finalY + 5;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('CUSTOMER TERMS & CONDITIONS', 14, y);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    const wrappedTerms = doc.splitTextToSize(pdfPlainText(customer.payment_terms) || '-', 182);
    doc.text(wrappedTerms, 14, y + 6);
    y += 10 + (wrappedTerms.length * 3.5);

    if (!documentSignatureFooterFits(y + staticBankDetailsHeight(doc) + 6, options.signatureImage)) {
        doc.addPage();
        y = 18;
    }

    const bankBottom = drawStaticBankDetails(doc, y);
    drawDocumentSignatureFooter(doc, bankBottom + 6, options.signatureImage);
    addPageNumbers(doc);
}

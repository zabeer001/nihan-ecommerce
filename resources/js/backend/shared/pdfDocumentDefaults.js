export const PDF_COMPANY_NAME = 'R-Locket (Bangladesh) Co., Ltd';
export const PDF_AUTHORIZED_SIGNATURE_URL = '/images/authorized-signature.png';
export const PDF_COMPANY_CONTACT_LINES = [
    'Address: Bhuigor, Fatullah, Narayangonj.  Mobile: 01730595720  Mail: mahmud@rlocketbd.com',
    'Register BIN No: 0005813670204',
];

export function drawDocumentHeader(doc, {
    documentTitle,
    documentNumberLabel,
    documentNumber,
    emphasized = false,
    fontScale = 1,
}) {
    doc.setTextColor(emphasized ? 0 : 17, emphasized ? 0 : 24, emphasized ? 0 : 39);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize((emphasized ? 18 : 17) * fontScale);
    doc.text(PDF_COMPANY_NAME, 105, 14, { align: 'center' });
    doc.setFont('helvetica', emphasized ? 'bold' : 'normal');
    doc.setFontSize((emphasized ? 8.5 : 7.5) * fontScale);
    doc.text(PDF_COMPANY_CONTACT_LINES[0], 105, 19, { align: 'center' });
    doc.setFont('helvetica', 'bold');
    doc.text(PDF_COMPANY_CONTACT_LINES[1], 105, 22.5, { align: 'center' });
    doc.setFont('helvetica', 'bold');
    doc.setFontSize((emphasized ? 16 : 15) * fontScale);
    doc.text(documentTitle, 105, 29, { align: 'center' });
    doc.setFontSize((emphasized ? 11 : 10) * fontScale);
    doc.text(`${documentNumberLabel}: ${documentNumber || '-'}`, 105, 35, { align: 'center' });
    return 38;
}

export function drawDocumentSignatureFooter(doc, currentY = 220, signatureImage = null, labels = {}) {
    const leftLabel = labels.left || 'Goods Receiver';
    const rightLabel = labels.right || 'For and on behalf of';
    const footerBottom = 272;
    let lineY = Math.max(currentY + (signatureImage ? 30 : 8), 258);

    if (lineY + 8 > footerBottom) {
        doc.addPage();
        lineY = 258;
    }

    doc.setTextColor(labels.emphasized ? 0 : 17, labels.emphasized ? 0 : 24, labels.emphasized ? 0 : 39);
    doc.setDrawColor(labels.emphasized ? 0 : 17, labels.emphasized ? 0 : 24, labels.emphasized ? 0 : 39);
    doc.setLineWidth(0.35);
    doc.setFont('helvetica', labels.emphasized ? 'bold' : 'normal');
    doc.setFontSize(labels.fontSize ?? (labels.emphasized ? 10 : 9));

    doc.line(20, lineY, 70, lineY);
    doc.text(leftLabel, 45, lineY + 5, { align: 'center' });

    if (signatureImage) {
        doc.addImage(signatureImage, 'PNG', 92.5, lineY - 25, 25, 23.5, undefined, 'FAST');
    }

    doc.line(130, lineY, 190, lineY);
    doc.text(rightLabel, 160, lineY + 5, { align: 'center' });

    return { y: lineY, bottom: lineY + 5 };
}

export function documentSignatureFooterFits(currentY, signatureImage = null) {
    const lineY = Math.max(currentY + (signatureImage ? 30 : 8), 258);

    return lineY + 8 <= 272;
}

export function pdfPlainText(rawValue) {
    if (rawValue === null || rawValue === undefined || rawValue === '') {
        return '';
    }

    const container = document.createElement('div');
    container.innerHTML = String(rawValue)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/(p|div|li|ul|ol|h[1-6])>/gi, '\n');

    return (container.textContent || '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .join('\n');
}

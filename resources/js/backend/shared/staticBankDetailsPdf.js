const BANK_ACCOUNTS = [
    {
        title: 'Bank Information',
        rows: [
            ['Account No:', '01030210003053'],
            ['Account Name:', 'R-Locket (Bangladesh) Co., Ltd'],
            ['Bank Name:', 'National Credit and Commerce Bank Ltd.'],
            ['Branch Name:', 'NCC Bank Bhaban Branch'],
            ['Bank Address:', 'NCC Bank Bhaban, 13/1 & 13/2 Toyenbee Circular Road, Motijheel C/A, Dhaka'],
            ['Branch Phone No:', '02-9592184, 02-9592185'],
            ['Swift Code:', 'NCCLBDDHNBB'],
        ],
    },
];

function drawBankAccount(doc, account, x, y, width, options = {}) {
    const labelWidth = 28;
    const valueWidth = width - labelWidth - 2;
    const fontSize = options.fontSize ?? 7.5;
    const lineHeight = options.lineHeight ?? 3.8;
    let rowY = y;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(options.headingFontSize ?? 9);
    doc.text(`${account.title}:`, x, rowY);
    rowY += 6;

    account.rows.forEach(([label, rowValue]) => {
        const wrappedValue = doc.splitTextToSize(rowValue, valueWidth);
        const rowHeight = Math.max(lineHeight, wrappedValue.length * lineHeight);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(fontSize);
        doc.text(label, x, rowY);
        doc.setFont('helvetica', options.boldValues ? 'bold' : 'normal');
        doc.text(wrappedValue, x + labelWidth, rowY);
        rowY += rowHeight + 1.2;
    });

    return rowY;
}

function staticBankDetailsHeight(doc, width = 182, options = {}) {
    const labelWidth = 28;
    const valueWidth = width - labelWidth - 2;
    const fontSize = options.fontSize ?? 7.5;
    const lineHeight = options.lineHeight ?? 3.8;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(fontSize);

    return 6 + BANK_ACCOUNTS[0].rows.reduce((height, [, rowValue]) => {
        const wrappedValue = doc.splitTextToSize(rowValue, valueWidth);
        return height + Math.max(lineHeight, wrappedValue.length * lineHeight) + 1.2;
    }, 0);
}

function drawStaticBankDetails(doc, y, options = {}) {
    const x = options.x ?? 14;
    const width = options.width ?? 182;

    doc.setTextColor(options.black ? 0 : 17, options.black ? 0 : 24, options.black ? 0 : 39);
    return drawBankAccount(doc, BANK_ACCOUNTS[0], x, y, width, options);
}

export { BANK_ACCOUNTS, drawStaticBankDetails, staticBankDetailsHeight };

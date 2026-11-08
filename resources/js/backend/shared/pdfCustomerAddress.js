function value(rawValue, fallback = '-') {
    return rawValue === null || rawValue === undefined || rawValue === '' ? fallback : String(rawValue);
}

function oneLine(rawValue) {
    return value(rawValue, '').replace(/\s+/g, ' ').trim();
}

function addressLines(customer, address) {
    const contactDetails = [
        customer?.contact_person ? `Attention To: ${customer.contact_person}` : '',
        customer?.mobile ? `Mobile: ${customer.mobile}` : '',
    ].filter(Boolean).join(', ');

    return [
        value(customer?.name, ''),
        oneLine(address),
        contactDetails,
        customer?.email ? `Mail: ${customer.email}` : '',
        customer?.bin_vat_number ? `BIN/VAT No: ${customer.bin_vat_number}` : '',
    ].filter(Boolean);
}

function drawAddressContent(doc, x, y, width, lines, options = {}) {
    const [customerName, ...addressDetails] = lines;
    const wrappedCustomerName = doc.splitTextToSize(value(customerName, ''), width - 6);
    const fontSize = options.fontSize ?? 8;
    const lineHeight = options.lineHeight ?? 3.2;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(fontSize);
    doc.text(wrappedCustomerName, x + 3, y + 4, { lineHeightFactor: 1.05 });

    if (addressDetails.length) {
        const detailsY = y + 4 + (wrappedCustomerName.length * lineHeight);
        const wrappedDetails = doc.splitTextToSize(addressDetails.join('\n'), width - 6);

        doc.setFont('helvetica', options.boldDetails ? 'bold' : 'normal');
        doc.text(wrappedDetails, x + 3, detailsY, { lineHeightFactor: 1.05 });
    }
}

function addressContentHeight(doc, width, lines, options = {}) {
    const [customerName, ...addressDetails] = lines;
    const customerNameLines = doc.splitTextToSize(value(customerName, ''), width - 6).length;
    const detailLines = addressDetails.length
        ? doc.splitTextToSize(addressDetails.join('\n'), width - 6).length
        : 0;
    const lineCount = Math.max(customerNameLines + detailLines, 1);

    return 6 + ((lineCount - 1) * (options.lineHeight ?? 3.2));
}

export function drawCustomerAddressBoxes(doc, customer, y, options = {}) {
    const x = 14;
    const width = 182;
    const columnWidth = width / 2;
    const headerHeight = 6;
    const customerAddress = customer?.address
        || customer?.shipping_address
        || customer?.delivery_address;
    const shippingLines = addressLines(customer, customerAddress);
    const billingLines = addressLines(customer, customerAddress);
    const boxHeight = headerHeight + Math.max(
        addressContentHeight(doc, columnWidth, shippingLines, options),
        addressContentHeight(doc, columnWidth, billingLines, options),
    );

    doc.setDrawColor(options.black ? 0 : 80);
    doc.setLineWidth(0.2);
    doc.rect(x, y, width, boxHeight);
    doc.line(x + columnWidth, y, x + columnWidth, y + boxHeight);
    doc.line(x, y + headerHeight, x + width, y + headerHeight);

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(options.headerFontSize ?? 9);
    doc.text('Ship To', x + 3, y + 4.3);
    doc.text('Bill To', x + columnWidth + 3, y + 4.3);

    drawAddressContent(doc, x, y + headerHeight, columnWidth, shippingLines, options);
    drawAddressContent(doc, x + columnWidth, y + headerHeight, columnWidth, billingLines, options);

    return y + boxHeight;
}

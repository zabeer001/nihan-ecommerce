import React, { useState } from 'react';
import { Download } from 'lucide-react';
import { apiRequest } from '@/shared/apiClient';
import { PDF_AUTHORIZED_SIGNATURE_URL } from './pdfDocumentDefaults';

function loadPdfImage(source) {
    return new Promise((resolve) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = source;
    });
}

function pdfFilename(pathname) {
    const parts = pathname.split('/').filter(Boolean);
    const id = parts.at(-1) || 'details';
    const resource = parts.at(-2) || 'document';

    return `${resource}-${id}.pdf`;
}

function DownloadPagePdfButton({ targetId = 'dashboard-page-content' }) {
    const [isGenerating, setIsGenerating] = useState(false);
    const [error, setError] = useState('');

    const downloadPdf = async () => {
        const content = document.getElementById(targetId);

        if (!content || isGenerating) {
            return;
        }

        setIsGenerating(true);
        setError('');

        try {
            const { jsPDF } = await import('jspdf');
            const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
            const signatureImage = await loadPdfImage(PDF_AUTHORIZED_SIGNATURE_URL);
            const orderMatch = window.location.pathname.match(/^\/dashboard\/order\/(\d+)\/?$/);
            const proformaInvoiceMatch = window.location.pathname.match(/^\/dashboard\/proforma-invoices\/(\d+)\/?$/);
            const deliveryChallanMatch = window.location.pathname.match(/^\/dashboard\/delivery-challan\/(\d+)\/?$/);
            const invoiceMatch = window.location.pathname.match(/^\/dashboard\/invoice\/(\d+)\/?$/);
            const paymentMatch = window.location.pathname.match(/^\/dashboard\/payments\/(\d+)\/?$/);

            if (orderMatch) {
                const [response, autoTableModule, generatorModule] = await Promise.all([
                    apiRequest(`/api/orders/${orderMatch[1]}`, { method: 'GET', auth: true }),
                    import('jspdf-autotable'),
                    import('./orderPdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;
                const order = response.data?.order || response.data?.sections?.order_details || {};

                doc.setProperties({ title: `Order ${order.order_number || orderMatch[1]}` });
                generatorModule.default(doc, autoTable, response.data || {}, { signatureImage });
            } else if (proformaInvoiceMatch) {
                const [response, autoTableModule, generatorModule] = await Promise.all([
                    apiRequest(`/api/proforma-invoices/${proformaInvoiceMatch[1]}`, { method: 'GET', auth: true }),
                    import('jspdf-autotable'),
                    import('./proformaInvoicePdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;

                doc.setProperties({ title: `Proforma Invoice ${response.data?.pi_number || proformaInvoiceMatch[1]}` });
                generatorModule.default(doc, autoTable, response.data || {}, { signatureImage });
            } else if (deliveryChallanMatch) {
                const [response, autoTableModule, generatorModule] = await Promise.all([
                    apiRequest(`/api/delivery-challans/${deliveryChallanMatch[1]}`, { method: 'GET', auth: true }),
                    import('jspdf-autotable'),
                    import('./deliveryChallanPdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;

                doc.setProperties({ title: `Delivery Challan ${response.data?.challan_number || deliveryChallanMatch[1]}` });
                generatorModule.default(doc, autoTable, response.data || {}, { signatureImage });
            } else if (invoiceMatch) {
                const [response, autoTableModule, generatorModule] = await Promise.all([
                    apiRequest(`/api/invoices/${invoiceMatch[1]}`, { method: 'GET', auth: true }),
                    import('jspdf-autotable'),
                    import('./invoicePdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;

                doc.setProperties({ title: `Invoice ${response.data?.invoice_number || invoiceMatch[1]}` });
                generatorModule.default(doc, autoTable, response.data || {}, { signatureImage });
            } else if (paymentMatch) {
                const [response, autoTableModule, generatorModule] = await Promise.all([
                    apiRequest(`/api/payments/${paymentMatch[1]}`, { method: 'GET', auth: true }),
                    import('jspdf-autotable'),
                    import('./paymentPdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;

                doc.setProperties({ title: `Payment Receipt ${response.data?.reference_number || paymentMatch[1]}` });
                generatorModule.default(doc, autoTable, response.data || {}, { signatureImage });
            } else {
                const [autoTableModule, generatorModule] = await Promise.all([
                    import('jspdf-autotable'),
                    import('./standardShowPagePdf'),
                ]);
                const autoTable = autoTableModule.default || autoTableModule.autoTable;
                const title = content.querySelector('h1')?.textContent?.trim() || 'Details';

                doc.setProperties({ title });
                generatorModule.default(doc, autoTable, content, { signatureImage });
            }

            doc.save(pdfFilename(window.location.pathname));
        } catch (downloadError) {
            console.error('PDF download failed:', downloadError);
            setError('Could not generate the PDF. Please try again.');
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="mb-4 flex flex-wrap items-center justify-end gap-3" data-pdf-ignore>
            {error ? <span className="text-sm text-error">{error}</span> : null}
            <button type="button" className="btn btn-success btn-sm" onClick={downloadPdf} disabled={isGenerating}>
                <Download className="size-4" />
                {isGenerating ? 'Generating PDF...' : 'Download PDF'}
            </button>
        </div>
    );
}

export default DownloadPagePdfButton;

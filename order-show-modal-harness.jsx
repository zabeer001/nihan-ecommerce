import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AddDeliveryChallanModal from './resources/js/backend/pages/order/orderShow/_components/AddDeliveryChallanModal.jsx';
import AddInvoiceModal from './resources/js/backend/pages/order/orderShow/_components/AddInvoiceModal.jsx';
import AddOrderItemModal from './resources/js/backend/pages/order/orderShow/_components/AddOrderItemModal.jsx';
import AddPaymentModal from './resources/js/backend/pages/order/orderShow/_components/AddPaymentModal.jsx';
import AddProformaInvoiceModal from './resources/js/backend/pages/order/orderShow/_components/AddProformaInvoiceModal.jsx';
import AddRawMaterialModal from './resources/js/backend/pages/order/orderShow/_components/AddRawMaterialModal.jsx';
import AddRawMaterialPurchaseModal from './resources/js/backend/pages/order/orderShow/_components/AddRawMaterialPurchaseModal/AddRawMaterialPurchaseModal.jsx';
import AddSupplierModal from './resources/js/backend/pages/order/orderShow/_components/AddSupplierModal.jsx';
import DeliveryChallanDetailsModal from './resources/js/backend/pages/order/orderShow/_components/DeliveryChallanDetailsModal.jsx';
import InvoiceDetailsModal from './resources/js/backend/pages/order/orderShow/_components/InvoiceDetailsModal.jsx';
import OrderItemDetailsModal from './resources/js/backend/pages/order/orderShow/_components/OrderItemDetailsModal.jsx';
import ProformaInvoiceDetailsModal from './resources/js/backend/pages/order/orderShow/_components/ProformaInvoiceDetailsModal.jsx';
import RawMaterialPurchaseDetailsModal from './resources/js/backend/pages/order/orderShow/_components/RawMaterialPurchaseDetailsModal.jsx';

const resultEl = document.getElementById('result');
const requests = [];
const order = {
    id: 1,
    order_number: 'ORD-001',
    customer_id: 1,
    currency: 'BDT',
    customer: { id: 1, name: 'Demo Customer' },
};
const invoice = {
    id: 11,
    invoice_number: 'INV-001',
    invoice_date: '2026-07-09',
    customer_id: 1,
    order_id: 1,
    currency: 'BDT',
    total_amount: 1000,
    paid_total: 200,
    due_total: 800,
    payment_status: 'partial',
    status: 'issued',
};
const proformaInvoice = {
    id: 21,
    pi_number: 'PI-001',
    pi_date: '2026-07-09',
    customer_id: 1,
    order_id: 1,
    currency: 'BDT',
    subtotal: 1000,
    bank_document_charge: 0,
    freight_courier_charge: 0,
    grand_total: 1000,
    status: 'draft',
};
const challan = {
    id: 31,
    challan_number: 'CH-001',
    challan_date: '2026-07-09',
    customer_id: 1,
    order_id: 1,
    delivery_qty: 10,
    delivery_unit: 'pcs',
    status: 'draft',
};
const orderItem = {
    id: 41,
    order_id: 1,
    item_name: 'Item A',
    description: 'Desc',
    size_type: 'M',
    quantity: 10,
    unit: 'pcs',
    unit_price: 20,
    line_total: 200,
};
const purchase = {
    id: 51,
    supplier_id: 61,
    order_id: 1,
    purchase_number: 'RMP-001',
    source_type: 'local_purchase',
    payment_mode: 'cash',
    purchase_date: '2026-07-09',
    currency: 'BDT',
    exchange_rate: 1,
    tax_amount: 0,
    shipping_cost: 0,
    customs_cost: 0,
    other_cost: 0,
    status: 'draft',
    grand_total: 0,
    supplier: {
        id: 61,
        mobile: '01700000000',
        company_name: 'Supplier A',
        supplier_type: 'local',
    },
    order: { id: 1, order_number: 'ORD-001' },
    items: [{
        id: 71,
        raw_material_id: 81,
        raw_material: { id: 81, name: 'Cotton' },
        received_quantity: 5,
        description: 'Cotton desc',
        unit: 'kg',
        quantity: 5,
        remarks: '',
    }],
};

window.localStorage.setItem('access_token', 'test-token');
window.fetch = async (input, options = {}) => {
    const url = typeof input === 'string' ? input : input.url;
    const method = (options.method || 'GET').toUpperCase();
    const parsed = new URL(url, window.location.origin);
    const body = options.body ? JSON.parse(options.body) : null;
    requests.push({ method, path: parsed.pathname, search: parsed.search, body });

    let payload = { data: { id: 999 } };
    if (method === 'GET' && parsed.pathname === '/api/raw-materials') {
        payload = { data: { data: [{ id: 81, name: 'Cotton' }] } };
    } else if (method === 'GET' && parsed.pathname === '/api/suppliers') {
        payload = { data: { data: [] } };
    } else if (method === 'GET' && parsed.pathname === '/api/raw-material-purchases/51') {
        payload = { data: purchase };
    } else if (parsed.pathname === '/api/suppliers') {
        payload = { data: { id: 62, ...body } };
    } else {
        payload = { data: { id: Number(parsed.pathname.split('/').pop()) || 999, ...body } };
    }

    return new Response(JSON.stringify(payload), {
        status: 200,
        headers: { 'content-type': 'application/json' },
    });
};

function wait(ms) {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function waitFor(check, label, timeout = 3000) {
    const startedAt = Date.now();
    while (Date.now() - startedAt < timeout) {
        const value = check();
        if (value) return value;
        await wait(25);
    }
    throw new Error(`Timed out waiting for ${label}`);
}

function setValue(element, value) {
    const prototype = Object.getPrototypeOf(element);
    const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');
    descriptor?.set ? descriptor.set.call(element, value) : element.value = value;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

function field(name) {
    const element = document.querySelector(`[name="${name}"]`);
    if (!element) throw new Error(`Missing field ${name}`);
    return element;
}

function clickText(text) {
    const element = [...document.querySelectorAll('button')].find((button) => button.textContent.includes(text));
    if (!element) throw new Error(`Missing button ${text}`);
    element.click();
}

async function clickTextEventually(text) {
    const element = await waitFor(
        () => [...document.querySelectorAll('button')].find((button) => button.textContent.includes(text)),
        `button ${text}`
    );
    element.click();
}

function submitButton() {
    const element = document.querySelector('button[type="submit"]');
    if (!element) throw new Error('Missing submit button');
    element.click();
}

function hasRequest(method, path) {
    return requests.some((request) => request.method === method && request.path === path);
}

async function waitForRequest(method, path) {
    await waitFor(() => hasRequest(method, path), `${method} ${path}`);
}

function makeQueryClient() {
    return new QueryClient({
        defaultOptions: {
            queries: { retry: false, refetchOnWindowFocus: false },
            mutations: { retry: false },
        },
    });
}

const root = createRoot(document.getElementById('root'));
async function renderModal(component) {
    root.render(<QueryClientProvider client={makeQueryClient()}>{component}</QueryClientProvider>);
    await waitFor(() => document.querySelector('.modal-open'), 'modal open');
    await wait(25);
}

async function runCreateModalTests() {
    await renderModal(<AddOrderItemModal isOpen order={order} onClose={() => {}} />);
    setValue(field('item_name'), 'Created Item');
    submitButton();
    await waitForRequest('POST', '/api/order-items');

    await renderModal(<AddProformaInvoiceModal isOpen order={order} onClose={() => {}} />);
    setValue(field('pi_number'), 'PI-CREATE');
    submitButton();
    await waitForRequest('POST', '/api/proforma-invoices');

    await renderModal(<AddInvoiceModal isOpen order={order} onClose={() => {}} />);
    setValue(field('invoice_number'), 'INV-CREATE');
    submitButton();
    await waitForRequest('POST', '/api/invoices');

    await renderModal(<AddPaymentModal isOpen order={order} invoices={[invoice]} currency="BDT" onClose={() => {}} />);
    setValue(field('invoice_id'), String(invoice.id));
    setValue(field('paid_amount'), '100');
    submitButton();
    await waitForRequest('POST', '/api/payments');

    await renderModal(<AddDeliveryChallanModal isOpen order={order} onClose={() => {}} />);
    setValue(field('challan_number'), 'CH-CREATE');
    submitButton();
    await waitForRequest('POST', '/api/delivery-challans');

    await renderModal(<AddRawMaterialModal isOpen order={order} onClose={() => {}} />);
    setValue(field('name'), 'Created Material');
    submitButton();
    await waitForRequest('POST', '/api/raw-materials');

    await renderModal(<AddSupplierModal isOpen order={order} onClose={() => {}} />);
    setValue(field('mobile'), '01900000000');
    setValue(field('company_name'), 'Created Supplier');
    submitButton();
    await waitForRequest('POST', '/api/suppliers');

    await renderModal(<AddRawMaterialPurchaseModal isOpen order={order} onClose={() => {}} />);
    await waitFor(() => document.querySelector('select option[value="81"]'), 'material option');
    setValue(field('supplier_mobile'), '01800000000');
    setValue(field('supplier_company_name'), 'Purchase Supplier');
    const materialSelect = [...document.querySelectorAll('select')].find((select) => [...select.options].some((option) => option.value === '81'));
    setValue(materialSelect, '81');
    submitButton();
    await waitForRequest('POST', '/api/raw-material-purchases');
}

async function runUpdateModalTests() {
    await renderModal(<OrderItemDetailsModal item={orderItem} order={order} currency="BDT" onClose={() => {}} />);
    clickText('Edit');
    await clickTextEventually('Continue Editing');
    await waitFor(() => field('item_name'), 'order item edit form');
    setValue(field('item_name'), 'Updated Item');
    submitButton();
    await waitForRequest('PUT', '/api/order-items/41');

    await renderModal(<ProformaInvoiceDetailsModal proformaInvoice={proformaInvoice} order={order} onClose={() => {}} />);
    clickText('Edit');
    await clickTextEventually('Continue Editing');
    await waitFor(() => field('pi_number'), 'pi edit form');
    setValue(field('pi_number'), 'PI-UPDATED');
    submitButton();
    await waitForRequest('PUT', '/api/proforma-invoices/21');

    await renderModal(<InvoiceDetailsModal invoice={invoice} order={order} onClose={() => {}} />);
    clickText('Edit');
    await clickTextEventually('Continue Editing');
    await waitFor(() => field('invoice_number'), 'invoice edit form');
    setValue(field('invoice_number'), 'INV-UPDATED');
    submitButton();
    await waitForRequest('PUT', '/api/invoices/11');

    await renderModal(<DeliveryChallanDetailsModal challan={challan} order={order} onClose={() => {}} />);
    clickText('Edit');
    await clickTextEventually('Continue Editing');
    await waitFor(() => field('challan_number'), 'challan edit form');
    setValue(field('challan_number'), 'CH-UPDATED');
    submitButton();
    await waitForRequest('PUT', '/api/delivery-challans/31');

    await renderModal(<RawMaterialPurchaseDetailsModal purchaseId={51} onClose={() => {}} />);
    await waitFor(() => document.body.textContent.includes('RMP-001'), 'purchase detail data');
    clickText('Edit');
    await clickTextEventually('Continue Editing');
    await waitFor(() => field('purchase_number'), 'purchase edit form');
    setValue(field('purchase_number'), 'RMP-UPDATED');
    await waitFor(() => document.querySelector('select option[value="81"]'), 'purchase edit material option');
    const purchaseEditMaterialSelect = [...document.querySelectorAll('select')].find((select) => [...select.options].some((option) => option.value === '81'));
    setValue(purchaseEditMaterialSelect, '81');
    submitButton();
    await waitForRequest('PUT', '/api/raw-material-purchases/51');
}

(async () => {
    try {
        await runCreateModalTests();
        await runUpdateModalTests();
        resultEl.textContent = JSON.stringify({ status: 'PASS', requests }, null, 2);
        console.log('ORDER_SHOW_MODAL_TEST_PASS');
    } catch (error) {
        resultEl.textContent = JSON.stringify({
            status: 'FAIL',
            message: error.message,
            stack: error.stack,
            requests,
            html: document.body.innerHTML.slice(0, 3000),
        }, null, 2);
        console.error('ORDER_SHOW_MODAL_TEST_FAIL', error);
    }
})();

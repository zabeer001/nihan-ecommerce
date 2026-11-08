import React, { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { UNIT_OPTIONS } from '@/backend/shared/unitOptions';
import { fetchCustomers } from '../customers/api/customerApi';
import { fetchOrder, fetchOrders } from '../order/api/orderApi';
import { fetchProformaInvoices } from '../proformaInvoices/api/proformaInvoiceApi';
import { createDeliveryChallan } from './api/deliveryChallanApi';
import VariantAllocationEditor, { computedVariantTotal } from './VariantAllocationEditor';

const LOCKED_FIELD_CLASSES = 'cursor-not-allowed bg-base-300/60 text-base-content/60';

const EMPTY_FORM = {
    customer_id: '',
    order_id: '',
    proforma_invoice_id: '',
    challan_number: '',
    challan_date: '',
    delivery_qty: '0',
    delivery_unit: '',
    delivery_person_name: '',
    package_info: '',
    gross_weight: '',
    net_weight: '',
    remarks: '',
    // A challan records goods that have been sent. Draft remains available when
    // needed, but delivered must be the default so a second partial shipment
    // correctly sees the first one in its remaining quantity.
    status: 'delivered',
    variant_lines: [],
};

function padDatePart(value) {
    return String(value).padStart(2, '0');
}

function generateChallanNumber(date = new Date()) {
    const year = date.getFullYear();
    const month = padDatePart(date.getMonth() + 1);
    const day = padDatePart(date.getDate());
    const hours = padDatePart(date.getHours());
    const minutes = padDatePart(date.getMinutes());
    const seconds = padDatePart(date.getSeconds());

    return `CH-${year}${month}${day}-${hours}${minutes}${seconds}`;
}

function normalizePayload(form) {
    const variantLines = Array.isArray(form.variant_lines)
        ? form.variant_lines
            .filter((line) => Number(line.quantity || 0) > 0)
            .map((line) => ({
                order_item_style_id: Number(line.order_item_style_id),
                quantity: Number(line.quantity),
            }))
        : [];

    return {
        ...form,
        customer_id: Number(form.customer_id),
        order_id: Number(form.order_id),
        proforma_invoice_id: form.proforma_invoice_id ? Number(form.proforma_invoice_id) : null,
        delivery_qty: variantLines.length ? computedVariantTotal(variantLines) : Number(form.delivery_qty || 0),
        gross_weight: form.gross_weight === '' ? null : Number(form.gross_weight),
        net_weight: form.net_weight === '' ? null : Number(form.net_weight),
        variant_lines: variantLines,
    };
}

function proformaInvoiceLabel(proformaInvoice) {
    if (!proformaInvoice) return '';

    const customerName = proformaInvoice.customer?.name || 'No customer';
    const orderNumber = proformaInvoice.order?.order_number || proformaInvoice.order?.production_order_number || 'No order';

    return `${proformaInvoice.pi_number || `PI #${proformaInvoice.id}`} · ${customerName} · ${orderNumber}`;
}

function DeliveryChallanForm({
    form,
    onFieldChange,
    onSubmit,
    isSaving,
    submitLabel,
    onGenerateChallanNumber,
    proformaSearch,
    selectedProformaLabel,
    onProformaSearchChange,
    onSelectProformaInvoice,
    onClearProformaInvoice,
    currentChallanId,
    autoComplete,
}) {
    const [orderSearch, setOrderSearch] = useState('');
    const [selectedOrderSearchId, setSelectedOrderSearchId] = useState('');
    const customersQuery = useQuery({
        queryKey: ['customers', 'delivery-challan-select'],
        queryFn: () => fetchCustomers({ perPage: 100 }),
        retry: false,
        refetchOnWindowFocus: false,
    });
    const ordersQuery = useQuery({
        queryKey: ['orders', 'delivery-challan-search', orderSearch],
        queryFn: () => fetchOrders({ search: orderSearch, perPage: 10, withoutDelivered: true }),
        enabled: String(orderSearch || '').trim().length > 0 && !selectedOrderSearchId,
        retry: false,
        refetchOnWindowFocus: false,
    });
    const proformaInvoicesQuery = useQuery({
        queryKey: ['proforma-invoices', 'delivery-challan-search', proformaSearch],
        queryFn: () => fetchProformaInvoices({ search: proformaSearch, perPage: 10 }),
        enabled: String(proformaSearch || '').trim().length > 0 && !form.proforma_invoice_id,
        retry: false,
        refetchOnWindowFocus: false,
    });
    const orderDetailsQuery = useQuery({
        queryKey: ['order-delivery-inventory', form.order_id],
        queryFn: () => fetchOrder(form.order_id),
        enabled: Boolean(form.order_id),
        retry: false,
        refetchOnWindowFocus: false,
    });
    const orderProformaInvoicesQuery = useQuery({
        queryKey: ['proforma-invoices', 'delivery-challan-order', selectedOrderSearchId],
        queryFn: () => fetchProformaInvoices({ orderId: selectedOrderSearchId, perPage: 1 }),
        enabled: Boolean(selectedOrderSearchId) && !form.proforma_invoice_id,
        retry: false,
        refetchOnWindowFocus: false,
    });

    const customers = customersQuery.data?.data?.data || [];
    const orders = ordersQuery.data?.data?.data || [];
    const proformaInvoices = proformaInvoicesQuery.data?.data?.data || [];
    const showProformaMatches = String(proformaSearch || '').trim().length > 0
        && !form.proforma_invoice_id
        && (proformaInvoicesQuery.isFetching || proformaInvoices.length > 0);
    const showOrderMatches = String(orderSearch || '').trim().length > 0
        && !selectedOrderSearchId
        && (ordersQuery.isFetching || orders.length > 0);
    const orderDetails = orderDetailsQuery.data?.data || null;
    const variantInventory = orderDetails?.variant_delivery_inventory || { variants: [], legacy_unallocated: [] };
    const selectedOrder = orderDetails?.order || null;
    const hasVariants = variantInventory.variants.length > 0;
    const isInventoryLoading = Boolean(form.order_id) && orderDetailsQuery.isFetching;
    const isFullyDeliveredOrder = !currentChallanId && selectedOrder?.delivery_status === 'delivered';
    const isMissingProformaInvoice = Boolean(form.order_id) && !form.proforma_invoice_id && !orderProformaInvoicesQuery.isFetching;
    const piFieldLocked = Boolean(form.proforma_invoice_id);
    const orderFieldLocked = Boolean(form.order_id);
    const customerFieldLocked = piFieldLocked || orderFieldLocked;
    const selectedOrderLabel = selectedOrder
        ? `${selectedOrder.order_number || `Order #${selectedOrder.id}`} · ${selectedOrder.customer?.name || 'No customer'}`
        : (orderSearch || `Order #${form.order_id}`);
    const selectedOrderNumber = selectedOrder
        ? (selectedOrder.order_number || `Order #${selectedOrder.id}`)
        : (form.order_id ? `Order #${form.order_id}` : '');

    const selectProformaInvoice = (proformaInvoice, selectedOrderId = '') => {
        setOrderSearch('');
        setSelectedOrderSearchId('');
        onSelectProformaInvoice(proformaInvoice, selectedOrderId);
    };

    const selectOrder = (order) => {
        setOrderSearch(`${order.order_number || `Order #${order.id}`} · ${order.customer?.name || 'No customer'}`);
        setSelectedOrderSearchId(String(order.id));
        onClearProformaInvoice();
        onFieldChange({ target: { name: 'customer_id', value: String(order.customer_id || order.customer?.id || '') } });
        onFieldChange({ target: { name: 'order_id', value: String(order.id) } });
    };

    useEffect(() => {
        const linkedProformaInvoice = orderProformaInvoicesQuery.data?.data?.data?.[0];

        if (selectedOrderSearchId && !form.proforma_invoice_id && linkedProformaInvoice) {
            selectProformaInvoice(linkedProformaInvoice, selectedOrderSearchId);
        }
    }, [orderProformaInvoicesQuery.data, selectedOrderSearchId, form.proforma_invoice_id]);

    const clearOrder = () => {
        setOrderSearch('');
        setSelectedOrderSearchId('');
        onClearProformaInvoice();
        onFieldChange({ target: { name: 'customer_id', value: '' } });
        onFieldChange({ target: { name: 'order_id', value: '' } });
    };

    return (
        <form className="grid gap-4 md:grid-cols-2" onSubmit={onSubmit} autoComplete={autoComplete}>
            <div className="form-control w-full">
                <span className="label-text mb-2">Challan Number</span>
                <div className="join w-full">
                    <input type="text" name="challan_number" className="input input-bordered join-item min-w-0 flex-1" value={form.challan_number} onChange={onFieldChange} />
                    <button type="button" className="btn btn-outline join-item" onClick={onGenerateChallanNumber}>
                        Auto Generate
                    </button>
                </div>
            </div>
            <label className="form-control w-full">
                <span className="label-text mb-2">Challan Date</span>
                <input type="date" name="challan_date" className="input input-bordered w-full" value={form.challan_date} onChange={onFieldChange} />
            </label>
            <div className="form-control w-full md:col-span-2">
                <span className="label-text mb-2">Search by PI</span>
                <input
                    type="text"
                    className={`input input-bordered w-full ${piFieldLocked ? LOCKED_FIELD_CLASSES : ''}`}
                    value={proformaSearch}
                    onChange={onProformaSearchChange}
                    placeholder="Type PI number, customer, or order number"
                    readOnly={piFieldLocked}
                />
                {form.proforma_invoice_id ? (
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
                        <span>
                            Selected: {selectedProformaLabel || proformaSearch || `PI #${form.proforma_invoice_id}`}
                            {' · Relationship fields are locked'}
                        </span>
                        <button type="button" className="btn btn-ghost btn-xs" onClick={onClearProformaInvoice}>
                            Change
                        </button>
                    </div>
                ) : null}
                {showProformaMatches ? (
                    <div className="mt-2 overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                        {proformaInvoicesQuery.isFetching ? <div className="px-3 py-2 text-sm text-base-content/60">Searching proforma invoices...</div> : null}
                        {!proformaInvoicesQuery.isFetching && proformaInvoices.map((proformaInvoice) => (
                            <button
                                key={proformaInvoice.id}
                                type="button"
                                className="block w-full border-b border-base-200 px-3 py-2 text-left last:border-b-0 hover:bg-base-200"
                                onClick={() => selectProformaInvoice(proformaInvoice)}
                            >
                                <span className="block text-sm font-semibold">{proformaInvoice.pi_number || `PI #${proformaInvoice.id}`}</span>
                                <span className="block text-xs text-base-content/60">
                                    {proformaInvoice.customer?.name || '-'} · {proformaInvoice.order?.order_number || proformaInvoice.order?.production_order_number || '-'}
                                </span>
                            </button>
                        ))}
                    </div>
                ) : null}
            </div>
            <div className="form-control w-full md:col-span-2">
                <span className="label-text mb-2">Search by Order</span>
                <input
                    type="text"
                    className={`input input-bordered w-full ${orderFieldLocked ? LOCKED_FIELD_CLASSES : ''}`}
                    value={form.order_id ? selectedOrderNumber : orderSearch}
                    onChange={(event) => {
                        if (form.order_id) {
                            onClearProformaInvoice();
                            onFieldChange({ target: { name: 'customer_id', value: '' } });
                            onFieldChange({ target: { name: 'order_id', value: '' } });
                        }

                        setOrderSearch(event.target.value);
                        setSelectedOrderSearchId('');
                    }}
                    placeholder="Type order number, production order, PO, reference, or customer"
                    readOnly={orderFieldLocked}
                />
                {form.order_id ? (
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
                        <span>
                            Selected: {selectedOrderLabel}
                            {orderProformaInvoicesQuery.isFetching ? ' · Finding linked PI...' : ''}
                        </span>
                        <button type="button" className="btn btn-ghost btn-xs" onClick={clearOrder}>
                            Change
                        </button>
                    </div>
                ) : null}
                {showOrderMatches ? (
                    <div className="mt-2 overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                        {ordersQuery.isFetching ? <div className="px-3 py-2 text-sm text-base-content/60">Searching orders...</div> : null}
                        {!ordersQuery.isFetching && orders.map((order) => (
                            <button
                                key={order.id}
                                type="button"
                                className="block w-full border-b border-base-200 px-3 py-2 text-left last:border-b-0 hover:bg-base-200"
                                onClick={() => selectOrder(order)}
                            >
                                <span className="block text-sm font-semibold">{order.order_number || `Order #${order.id}`}</span>
                                <span className="block text-xs text-base-content/60">
                                    {order.customer?.name || '-'} · PO: {order.po_number || '-'} · Reference: {order.reference_number || '-'}
                                </span>
                            </button>
                        ))}
                        {!ordersQuery.isFetching && !orders.length ? (
                            <div className="px-3 py-2 text-sm text-base-content/60">No matching orders found.</div>
                        ) : null}
                    </div>
                ) : null}
            </div>
            <label className="form-control w-full">
                <span className="label-text mb-2">Customer</span>
                <select name="customer_id" className={`select select-bordered w-full ${customerFieldLocked ? LOCKED_FIELD_CLASSES : ''}`} value={form.customer_id} onChange={onFieldChange} required disabled={customerFieldLocked}>
                    <option value="">Select customer</option>
                    {customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}
                </select>
            </label>
            <label className="form-control w-full">
                <span className="label-text mb-2">Selected Order</span>
                <input
                    type="text"
                    className={`input input-bordered w-full ${orderFieldLocked ? LOCKED_FIELD_CLASSES : ''}`}
                    value={form.order_id ? selectedOrderLabel : ''}
                    placeholder="Select a PI or search for an order above"
                    readOnly
                />
                <input type="hidden" name="order_id" value={form.order_id} required readOnly />
            </label>
            {isFullyDeliveredOrder ? (
                <div className="alert alert-warning md:col-span-2">
                    This order is already fully delivered. No additional delivery challan can be created.
                </div>
            ) : null}
            {isMissingProformaInvoice ? (
                <div className="alert alert-warning md:col-span-2">
                    <span>A proforma invoice must be created for this order before making a delivery challan.</span>
                    <Link href="/dashboard/proforma-invoices/create" className="btn btn-sm btn-outline">
                        Create PI
                    </Link>
                </div>
            ) : null}
            {isInventoryLoading ? (
                <div className="rounded-lg border border-base-300 bg-base-200 p-4 text-sm md:col-span-2">Loading variant inventory...</div>
            ) : hasVariants ? (
                <VariantAllocationEditor
                    inventory={variantInventory}
                    value={form.variant_lines}
                    onChange={(variantLines) => onFieldChange({ target: { name: 'variant_lines', value: variantLines } })}
                    currentChallanId={currentChallanId}
                    currentChallanDate={form.challan_date}
                    readOnly={isFullyDeliveredOrder}
                />
            ) : !isFullyDeliveredOrder ? (
                <>
                    <label className="form-control w-full">
                        <span className="label-text mb-2">Delivery Qty</span>
                        <input type="number" min="0" step="any" name="delivery_qty" className="input input-bordered w-full" value={form.delivery_qty} onChange={onFieldChange} />
                    </label>
                    <label className="form-control w-full">
                        <span className="label-text mb-2">Delivery Unit</span>
                        <select name="delivery_unit" className="select select-bordered w-full" value={form.delivery_unit} onChange={onFieldChange}>
                            {UNIT_OPTIONS.map((option) => (
                                <option key={option.value || 'empty'} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </>
            ) : null}
            <label className="form-control w-full">
                <span className="label-text mb-2">Status</span>
                <select name="status" className="select select-bordered w-full" value={form.status} onChange={onFieldChange}>
                    <option value="draft">Draft</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            <label className="form-control w-full">
                <span className="label-text mb-2">Delivery Person</span>
                <input type="text" name="delivery_person_name" className="input input-bordered w-full" value={form.delivery_person_name} onChange={onFieldChange} />
            </label>
            <label className="form-control w-full">
                <span className="label-text mb-2">Package Info</span>
                <input type="text" name="package_info" className="input input-bordered w-full" value={form.package_info} onChange={onFieldChange} />
            </label>
            <label className="form-control w-full">
                <span className="label-text mb-2">Gross Weight</span>
                <input type="number" min="0" step="any" name="gross_weight" className="input input-bordered w-full" value={form.gross_weight} onChange={onFieldChange} />
            </label>
            <label className="form-control w-full">
                <span className="label-text mb-2">Net Weight</span>
                <input type="number" min="0" step="any" name="net_weight" className="input input-bordered w-full" value={form.net_weight} onChange={onFieldChange} />
            </label>
            <label className="form-control w-full md:col-span-2">
                <span className="label-text mb-2">Remarks</span>
                <textarea name="remarks" className="textarea textarea-bordered min-h-24 w-full" value={form.remarks} onChange={onFieldChange} />
            </label>
            <div className="md:col-span-2">
                <button type="submit" className="btn btn-success" disabled={isSaving || isInventoryLoading || !form.order_id || !form.proforma_invoice_id || isFullyDeliveredOrder}>
                    {isSaving ? 'Saving...' : submitLabel}
                </button>
            </div>
        </form>
    );
}

function CreateDeliveryChallanPage() {
    const [error, setError] = useState('');
    const [form, setForm] = useState(EMPTY_FORM);
    const [proformaSearch, setProformaSearch] = useState('');
    const [selectedProformaLabel, setSelectedProformaLabel] = useState('');
    const createMutation = useMutation({
        mutationFn: () => createDeliveryChallan(normalizePayload(form)),
        onSuccess: () => {
            window.location.href = '/dashboard/delivery-challan?created=1';
        },
        onError: (err) => setError(err.message || 'Failed to create delivery challan.'),
    });

    const onFieldChange = (event) => {
        const { name, value } = event.target;
        setForm((current) => ({
            ...current,
            [name]: value,
            ...(name === 'order_id' ? { variant_lines: [] } : {}),
        }));
    };

    const onProformaSearchChange = (event) => {
        setProformaSearch(event.target.value);
        setSelectedProformaLabel('');
        setForm((current) => ({ ...current, proforma_invoice_id: '' }));
    };

    const onSelectProformaInvoice = (proformaInvoice, selectedOrderId = '') => {
        const label = proformaInvoiceLabel(proformaInvoice);

        setProformaSearch(label);
        setSelectedProformaLabel(label);
        setForm((current) => ({
            ...current,
            proforma_invoice_id: String(proformaInvoice.id || ''),
            customer_id: String(proformaInvoice.customer_id || proformaInvoice.customer?.id || current.customer_id || ''),
            order_id: String(selectedOrderId || proformaInvoice.order_id || proformaInvoice.order?.id || current.order_id || ''),
            variant_lines: [],
        }));
    };

    const onClearProformaInvoice = () => {
        setProformaSearch('');
        setSelectedProformaLabel('');
        setForm((current) => ({ ...current, proforma_invoice_id: '' }));
    };

    const onGenerateChallanNumber = () => {
        setForm((current) => ({ ...current, challan_number: generateChallanNumber() }));
    };

    const onSubmit = (event) => {
        event.preventDefault();
        setError('');

        if (!form.proforma_invoice_id) {
            setError('Please create and select a proforma invoice before making a delivery challan.');
            return;
        }

        createMutation.mutate();
    };

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Add Delivery Challan</h1>
                            <p className="mt-2 text-sm text-base-content/70">Select an existing PI to create a delivery record.</p>
                        </div>
                        <Link href="/dashboard/delivery-challan" className="btn btn-outline">Back</Link>
                    </div>
                </div>
            </section>
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {error ? <div className="alert alert-error mb-4 py-2 text-sm">{error}</div> : null}
                    <DeliveryChallanForm
                        form={form}
                        onFieldChange={onFieldChange}
                        onSubmit={onSubmit}
                        isSaving={createMutation.isPending}
                        submitLabel="Create Delivery Challan"
                        onGenerateChallanNumber={onGenerateChallanNumber}
                        proformaSearch={proformaSearch}
                        selectedProformaLabel={selectedProformaLabel}
                        onProformaSearchChange={onProformaSearchChange}
                        onSelectProformaInvoice={onSelectProformaInvoice}
                        onClearProformaInvoice={onClearProformaInvoice}
                        autoComplete="off"
                    />
                </div>
            </section>
        </div>
    );
}

export default CreateDeliveryChallanPage;
export { DeliveryChallanForm, EMPTY_FORM, generateChallanNumber, normalizePayload };

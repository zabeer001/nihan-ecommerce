import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { usePermissions } from '../../layouts/PermissionsContext';
import { Field, input, request } from '../catalog/catalogUi';
import OrderForm from '../catalog/components/OrderForm';
import OrderProductPicker from '../catalog/components/OrderProductPicker';
import OrderInvoice, { OrderItems } from '../catalog/components/OrderInvoice';

const emptyOrder = () => ({
    submission_key: crypto.randomUUID(), customer_name: '', phone: '', email: '', address: '', delivery: '0', items: [],
});
const contactFields = { customer_name: 'Customer name', phone: 'Phone', email: 'Email', address: 'Delivery address' };

export default function OrderPage({ mode, orderId }) {
    const { can, loading } = usePermissions();
    const canView = can('orders.view');
    const canUpdate = can('orders.update');
    const allowed = mode === 'create' ? can('orders.create') : canView && (mode === 'invoice' || canUpdate);
    const queryClient = useQueryClient();
    const [draft, setDraft] = useState(emptyOrder);
    const [contact, setContact] = useState({});
    const [quantities, setQuantities] = useState({});
    const [newItems, setNewItems] = useState([]);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [created, setCreated] = useState(null);
    const queryKey = ['admin-order', orderId];
    const orderQuery = useQuery({
        queryKey, queryFn: () => request(`orders/${orderId}`),
        enabled: !loading && allowed && mode !== 'create', staleTime: 0,
    });
    const detail = orderQuery.data;

    useEffect(() => {
        setError('');
    }, [mode, orderId]);

    useEffect(() => {
        if (detail) {
            setNewItems([]);
            setQuantities(Object.fromEntries(detail.items.map(item => [item.id, item.quantity])));
            setContact(Object.fromEntries(Object.keys(contactFields).map(key => [key, detail.order[key] ?? ''])));
        }
    }, [detail]);

    async function run(action) {
        setBusy(true); setError('');
        try { await action(); }
        catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    }

    async function saveContact(event) {
        event.preventDefault();
        await run(async () => {
            await request(`orders/${orderId}`, { method: 'PUT', body: { ...contact, items: detail.items.map(item => ({ id: item.id, quantity: Number(quantities[item.id] ?? item.quantity) })),
                new_items: newItems.map(item => ({ variant_id: item.variant_id, quantity: Number(item.quantity) })) } });
            await queryClient.invalidateQueries({ queryKey });
            router.visit(`/dashboard/orders/${orderId}/invoice?saved=1`);
        });
    }

    function addProduct(variant) {
        const existing = detail.items.find(item => item.variant_id === variant.id);
        if (existing) {
            const maximum = Math.min(Number(variant.stock) + Number(existing.quantity), 1000000);
            setQuantities(current => ({ ...current, [existing.id]: Math.min((Number(current[existing.id] ?? existing.quantity) || 0) + 1, maximum) }));
            return;
        }
        setNewItems(current => {
            const selected = current.find(item => item.variant_id === variant.id);
            if (selected) return current.map(item => item.variant_id === variant.id
                ? { ...item, quantity: Math.min((Number(item.quantity) || 0) + 1, Number(variant.stock), 1000000) } : item);
            if (variant.stock < 1 || detail.items.length + current.length >= 100) return current;
            return [...current, {
                id: `new:${variant.id}`, variant_id: variant.id, product_name: variant.product_name,
                sku: variant.sku, attributes: variant.attributes || {}, quantity: 1,
                price_cents: variant.price_cents, isNew: true,
            }];
        });
    }

    const previewItems = detail && [...detail.items, ...newItems].map(item => ({
        ...item, quantity: quantities[item.id] ?? item.quantity,
        total_cents: Number(quantities[item.id] ?? item.quantity) * item.price_cents,
    }));
    const previewSubtotal = previewItems?.reduce((sum, item) => sum + item.total_cents, 0) ?? 0;
    const preview = detail && { ...detail, items: previewItems, order: {
        ...detail.order, subtotal_cents: previewSubtotal, total_cents: previewSubtotal + Number(detail.order.delivery_cents),
    } };

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!allowed) return <p role="alert" className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h1 className="text-3xl font-bold">{{ create: 'Point of sale', edit: 'Edit order', invoice: 'Order invoice' }[mode]}{orderId && ` #${orderId}`}</h1>
            <div className="flex flex-wrap gap-2">
                <Link className="btn" href="/dashboard/orders">Back to orders</Link>
                {mode !== 'create' && <>
                    {mode !== 'invoice' && <Link className="btn" href={`/dashboard/orders/${orderId}/invoice`}>View invoice</Link>}
                    {canUpdate && mode !== 'edit' && <Link className="btn" href={`/dashboard/orders/${orderId}/edit`}>Edit order</Link>}
                </>}
            </div>
        </div>
        {(error || orderQuery.error) && <p role="alert" className="alert alert-error print:hidden">{error || orderQuery.error.message}</p>}
        {mode === 'invoice' && detail && new URLSearchParams(window.location.search).has('saved') &&
            <p role="status" className="alert alert-success print:hidden">Order saved. Customer details and order items are up to date.</p>}
        {mode !== 'create' && orderQuery.isPending && <p role="status">Loading order…</p>}
        {mode === 'create' && (created ? <div className="card bg-base-100 p-5 gap-4">
            <p role="status">Order #{created.id} created successfully.</p>
            <button className="btn btn-primary" onClick={() => { setCreated(null); setDraft(emptyOrder()); }}>Create another order</button>
        </div> : <fieldset disabled={busy}>
            <OrderForm order={draft} setOrder={setDraft} run={run}
                onCancel={() => router.visit('/dashboard/orders')}
                onCreated={order => {
                    if (canView) router.visit(`/dashboard/orders/${order.id}/invoice`);
                    else setCreated(order);
                }} />
        </fieldset>)}
        {detail && mode === 'edit' && <div className={detail.can_add_products ? 'grid items-start gap-5 xl:grid-cols-2' : 'space-y-4'}>
            {detail.can_add_products && <fieldset disabled={busy} className="min-w-0">
                <OrderProductPicker items={previewItems} onAdd={addProduct}
                    reservedQuantities={Object.fromEntries(detail.items.map(item => [item.variant_id, item.quantity]))} />
            </fieldset>}
            <form onSubmit={saveContact} className="card bg-base-100 p-5 gap-4">
                {!detail.can_add_products && <p role="status" className="alert">Products cannot be added to {detail.order.status} orders.</p>}
                <p>Saving updates this order and the linked customer's profile. Other orders keep their saved details.</p>
                <fieldset disabled={busy} className="grid gap-4 md:grid-cols-2">
                    {Object.entries(contactFields).map(([name, label]) => <Field key={name} label={label}>
                        {name === 'address' ? <textarea className="textarea textarea-bordered w-full" required maxLength={2000}
                            value={contact[name] ?? ''} onChange={event => setContact({ ...contact, [name]: event.target.value })} />
                            : <input className={input} type={name === 'email' ? 'email' : name === 'phone' ? 'tel' : 'text'}
                                required={name !== 'email'} maxLength={name === 'phone' ? 40 : 255}
                                value={contact[name] ?? ''} onChange={event => setContact({ ...contact, [name]: event.target.value })} />}
                    </Field>)}
                    <div className="md:col-span-2 space-y-4"><h2 className="text-xl font-bold">Order items and quantities</h2>
                        <OrderItems detail={preview} onQuantityChange={(id, quantity) => {
                            if (String(id).startsWith('new:')) setNewItems(current => current.map(item => item.id === id ? { ...item, quantity } : item));
                            else setQuantities(current => ({ ...current, [id]: quantity }));
                        }} onRemove={id => setNewItems(current => current.filter(item => item.id !== id))} />
                        <p>Existing unit prices and delivery charge stay the same. New products use current prices when saved.</p>
                    </div>
                    <button className="btn btn-primary md:col-span-2">Save order</button>
                </fieldset>
            </form>
        </div>}
        {detail && mode === 'invoice' && <OrderInvoice detail={detail} />}
        {busy && <p role="status" className="print:hidden">Saving / loading…</p>}
        {mode === 'invoice' && <style>{'@media print { body * { visibility:hidden; } #order-invoice, #order-invoice * { visibility:visible; } #order-invoice { position:absolute; left:0; top:0; width:100%; } #order-invoice .print\\:hidden { display:none; } }'}</style>}
    </section>;
}

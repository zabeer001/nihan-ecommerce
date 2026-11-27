import OrderProductPicker from './OrderProductPicker';
import { request, money, cents, Field, input } from '../catalogUi';

export default function OrderForm({ order, setOrder, run, onCreated, onCancel }) {
  function addProduct(variant) {
    setOrder(current => {
      const existing = current.items.find(item => item.variant_id === variant.id);
      if (existing) {
        if (Number(existing.quantity) >= Math.min(variant.stock, 1000000)) return current;
        return { ...current, items: current.items.map(item => item.variant_id === variant.id
          ? { ...item, quantity: (Number(item.quantity) || 0) + 1, stock: variant.stock } : item) };
      }
      if (variant.stock < 1 || current.items.length >= 100) return current;
      return { ...current, items: [...current.items, {
        variant_id: variant.id, quantity: 1, stock: variant.stock, price_cents: variant.price_cents,
        product_name: variant.product_name, sku: variant.sku, label: variant.name || '', image_url: variant.image_url,
      }] };
    });
  }

  function quantity(id, value) {
    setOrder(current => ({ ...current, items: current.items.map(item => item.variant_id === id ? { ...item, quantity: value } : item) }));
  }

  async function checkout(event) {
    event.preventDefault();
    await run(async () => {
      const result = await request('orders', { method: 'POST', body: {
        submission_key: order.submission_key, customer_name: order.customer_name, phone: order.phone,
        email: order.email, address: order.address, delivery_cents: cents(order.delivery),
        items: order.items.map(item => ({ variant_id: item.variant_id, quantity: Number(item.quantity) })),
      } });
      onCreated(result);
    });
  }

  const subtotal = order.items.reduce((sum, item) => sum + item.price_cents * (Number(item.quantity) || 0), 0);
  const itemCount = order.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);

  return <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_25rem]">
    <OrderProductPicker items={order.items} onAdd={addProduct} />

    <form onSubmit={checkout} className="min-w-0 space-y-4 xl:sticky xl:top-20" aria-label="Order checkout">
      <section className="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5">
        <div className="mb-4 flex items-center justify-between gap-3">
          <h2 className="text-xl font-bold">Cart <span className="badge badge-neutral">{itemCount}</span></h2>
          <button type="button" className="btn btn-ghost btn-sm" disabled={!order.items.length} onClick={() => setOrder(current => ({ ...current, items: [] }))}>Clear cart</button>
        </div>
        {!order.items.length ? <div role="status" className="rounded-box border border-dashed border-base-300 py-10 text-center">
          <p className="font-medium">Your cart is empty</p><p className="mt-1 text-sm opacity-60">Choose products to start an order.</p>
        </div> : <div className="max-h-96 overflow-y-auto divide-y divide-base-300">
          {order.items.map(item => <div key={item.variant_id} className="space-y-2 py-3 first:pt-0">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0"><p className="font-semibold leading-snug">{item.product_name}</p>
                <p className="break-all text-xs opacity-60">{item.label ? `${item.label} · ` : ''}{item.sku}</p>
                <p className="mt-1 text-sm opacity-70">{money(item.price_cents)} each</p>
              </div>
              <button type="button" className="btn btn-ghost btn-xs text-error" aria-label={`Remove ${item.product_name} / ${item.sku}`}
                onClick={() => setOrder(current => ({ ...current, items: current.items.filter(line => line.variant_id !== item.variant_id) }))}>Remove</button>
            </div>
            <div className="flex items-center justify-between gap-2">
              <div className="join">
                <button type="button" className="btn btn-sm join-item" aria-label={`Decrease quantity for ${item.sku}`} disabled={Number(item.quantity) <= 1}
                  onClick={() => quantity(item.variant_id, Math.max(1, Number(item.quantity) - 1))}>−</button>
                <input type="number" className="input input-bordered input-sm join-item w-16 text-center" required min="1" max={Math.min(item.stock, 1000000)}
                  aria-label={`Quantity for ${item.sku}`} value={item.quantity} onChange={event => quantity(item.variant_id, event.target.value)} />
                <button type="button" className="btn btn-sm join-item" aria-label={`Increase quantity for ${item.sku}`} disabled={Number(item.quantity) >= Math.min(item.stock, 1000000)}
                  onClick={() => quantity(item.variant_id, (Number(item.quantity) || 0) + 1)}>+</button>
              </div>
              <strong className="text-sm">{money(item.price_cents * (Number(item.quantity) || 0))}</strong>
            </div>
          </div>)}
        </div>}
        {order.items.length >= 100 && <p className="mt-3 text-sm">Cart limit: 100 product variants.</p>}
      </section>

      <section className="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5 space-y-3">
        <h2 className="text-lg font-bold">Customer & delivery</h2>
        <Field label="Customer name"><input className={input} required maxLength={255} autoComplete="name" value={order.customer_name} onChange={event => setOrder(current => ({ ...current, customer_name: event.target.value }))} /></Field>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1">
          <Field label="Phone"><input type="tel" className={input} required maxLength={40} autoComplete="tel" value={order.phone} onChange={event => setOrder(current => ({ ...current, phone: event.target.value }))} /></Field>
          <Field label="Email (optional)"><input type="email" className={input} maxLength={255} autoComplete="email" value={order.email} onChange={event => setOrder(current => ({ ...current, email: event.target.value }))} /></Field>
        </div>
        <Field label="Delivery address"><textarea className="textarea textarea-bordered w-full" required maxLength={2000} rows={2} autoComplete="street-address" value={order.address} onChange={event => setOrder(current => ({ ...current, address: event.target.value }))} /></Field>
        <Field label="Delivery charge (BDT)"><input type="number" className={input} required min="0" max="1000000" step="0.01" value={order.delivery} onChange={event => setOrder(current => ({ ...current, delivery: event.target.value }))} /></Field>
      </section>

      <section className="rounded-box border border-base-300 bg-base-100 p-4 sm:p-5 space-y-3">
        <div className="flex justify-between text-sm"><span>Subtotal</span><span>{money(subtotal)}</span></div>
        <div className="flex justify-between text-sm"><span>Delivery</span><span>{money(cents(order.delivery))}</span></div>
        <div className="flex items-center justify-between border-t border-base-300 pt-3 text-xl font-bold"><span>Total due</span><span>{money(subtotal + cents(order.delivery))}</span></div>
        <p className="text-sm opacity-70">Payment: Cash on delivery</p>
        <button type="submit" className="btn btn-primary w-full" disabled={!order.items.length}>Place COD order · {money(subtotal + cents(order.delivery))}</button>
        <button type="button" className="btn btn-ghost w-full" onClick={onCancel}>Cancel</button>
        <p className="text-xs opacity-50">Final prices and available stock are checked when you place the order.</p>
      </section>
    </form>
  </div>;
}

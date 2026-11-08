import { request, money, cents, Field, input } from '../catalogUi';
export default function OrderForm({
  order,
  setOrder,
  run,
  setDetail,
  load,
  variantSearch,
  setVariantSearch,
  options,
  setOptions
}) {
  return <form className="card bg-base-100 p-5 gap-4" onSubmit={e => {
    e.preventDefault();
    run(async () => {
      const {
        delivery,
        ...data
      } = order;
      const result = await request('orders', {
        method: 'POST',
        body: {
          ...data,
          delivery_cents: cents(delivery)
        }
      });
      setOrder(null);
      setDetail(await request(`orders/${result.id}`));
      await load();
    });
  }}>
    <h2 className="text-xl font-bold">New COD order</h2>
    {['customer_name', 'phone', 'email', 'address', 'delivery'].map(key => <Field key={key} label={key.replaceAll('_', ' ') + (key === 'delivery' ? ' (BDT)' : '')}>
      <input className={input} required={key !== 'email'} type={key === 'email' ? 'email' : key === 'delivery' ? 'number' : 'text'} min={key === 'delivery' ? 0 : undefined} step={key === 'delivery' ? '0.01' : undefined} value={order[key]} onChange={e => setOrder({
        ...order,
        [key]: e.target.value
      })} />
    </Field>)}
    <div className="flex gap-2">
      <input className={input} placeholder="Search SKU or product" value={variantSearch} onChange={e => setVariantSearch(e.target.value)} />
      <button type="button" className="btn" onClick={() => run(async () => setOptions((await request(`variants?search=${encodeURIComponent(variantSearch)}`)).data))}>Find variants</button>
    </div>
    <select className="select select-bordered w-full" value="" onChange={e => {
      const v = options.find(v => v.id === Number(e.target.value));
      if (v && !order.items.some(i => i.variant_id === v.id)) setOrder({
        ...order,
        items: [...order.items, {
          variant_id: v.id,
          quantity: 1,
          label: `${v.product_name} / ${v.sku}`,
          price_cents: v.price_cents
        }]
      });
    }}>
      <option value="">Add variant (first 30 matches; refine search)</option>
      {options.filter(v => v.active).map(v => <option key={v.id} value={v.id}>{v.product_name} / {v.sku} — {money(v.price_cents)} — Stock {v.stock}</option>)}
    </select>
    {order.items.map((item, index) => <div className="flex gap-3 items-center" key={item.variant_id}>
      <span>{item.label}</span>
      <input aria-label="Quantity" className="input input-bordered w-24" type="number" min="1" required value={item.quantity} onChange={e => setOrder({
        ...order,
        items: order.items.map((i, n) => n === index ? {
          ...i,
          quantity: Number(e.target.value)
        } : i)
      })} />
      <button type="button" className="btn btn-sm" onClick={() => setOrder({
        ...order,
        items: order.items.filter((_, n) => n !== index)
      })}>Remove</button>
    </div>)}
    <p>Estimated total: {money(order.items.reduce((sum, i) => sum + i.price_cents * i.quantity, 0) + cents(order.delivery))}. Final prices are calculated by the server.</p>
    <button disabled={!order.items.length} className="btn btn-primary">Place COD order</button>
    <button type="button" className="btn" onClick={() => setOrder(null)}>Close</button>
  </form>;
}

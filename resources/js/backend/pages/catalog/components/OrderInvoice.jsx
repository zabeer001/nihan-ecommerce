import { money } from '../catalogUi';

export function OrderItems({ detail, onQuantityChange, onRemove }) {
  return <>
    <div className="overflow-x-auto">
      <table className="table">
        <thead><tr><th>Product / SKU</th><th>Qty</th><th>Price</th><th>Total</th>{onRemove && <th>Selection</th>}</tr></thead>
        <tbody>{detail.items.map(item => <tr key={item.id}>
          <td>{item.product_name}<br />{item.sku} · {JSON.stringify(item.attributes)}</td>
          <td>{onQuantityChange ? <input type="number" className="input input-bordered w-24" min="1" max="1000000" required
            aria-label={`Quantity for ${item.product_name} (${item.sku})`} value={item.quantity}
            onChange={event => onQuantityChange(item.id, event.target.value)} /> : item.quantity}</td><td>{money(item.price_cents)}</td><td>{money(item.total_cents)}</td>
          {onRemove && <td>{item.isNew && <button type="button" className="btn btn-sm" onClick={() => onRemove(item.id)}>Remove selection</button>}</td>}
        </tr>)}</tbody>
      </table>
    </div>
    <p>Subtotal: {money(detail.order.subtotal_cents)}<br />
      Delivery: {money(detail.order.delivery_cents)}<br />
      <strong>Total: {money(detail.order.total_cents)}</strong>
    </p>
  </>;
}

export default function OrderInvoice({ detail }) {
  return <article id="order-invoice" className="card bg-base-100 p-6 gap-4">
    <h2 className="text-2xl font-bold">Invoice #{detail.order.id}</h2>
    <p>{detail.order.created_at} · {detail.order.status} · {detail.order.payment_method} · {detail.order.currency} · {detail.order.source === 'storefront' ? 'Storefront' : 'Staff'}</p>
    <p>{detail.order.customer_name}<br />{detail.order.phone}<br />
      {detail.order.email && <>{detail.order.email}<br /></>}
      <span className="whitespace-pre-wrap">{detail.order.address}</span>
    </p>
    <OrderItems detail={detail} />
    <div className="print:hidden"><button type="button" className="btn" onClick={() => window.print()}>Print invoice</button></div>
  </article>;
}

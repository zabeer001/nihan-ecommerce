import { request, money } from '../catalogUi';
export default function OrderInvoice({
  canUpdate,
  detail,
  run,
  setDetail,
  load
}) {
  return <article id="order-invoice" className="card bg-base-100 p-6 gap-4">
    <h2 className="text-2xl font-bold">Invoice #{detail.order.id}</h2>
    <p>{detail.order.created_at} · {detail.order.status} · COD · BDT</p>
    <p>
      {detail.order.customer_name}
      <br />
      {detail.order.phone}
      {detail.order.email}
      <br />
      {detail.order.address}
    </p>
    <table className="table">
      <thead>
        <tr>
          <th>Product / SKU</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>{detail.items.map(i => <tr key={i.id}>
          <td>
            {i.product_name}
            <br />
            {i.sku}
             · 
            {JSON.stringify(i.attributes)}
          </td>
          <td>{i.quantity}</td>
          <td>{money(i.price_cents)}</td>
          <td>{money(i.total_cents)}</td>
        </tr>)}</tbody>
    </table>
    <p>
      Subtotal: 
      {money(detail.order.subtotal_cents)}
      <br />
      Delivery: 
      {money(detail.order.delivery_cents)}
      <br />
      <strong>Total: {money(detail.order.total_cents)}</strong>
    </p>
    <div className="flex gap-3 print:hidden">
      <button className="btn" onClick={() => window.print()}>Print invoice</button>
      {canUpdate && !['Delivered', 'Cancelled'].includes(detail.order.status) && <select className="select select-bordered" value="" onChange={e => {
        const status = e.target.value;
        if (status) run(async () => {
          await request(`orders/${detail.order.id}/status`, {
            method: 'PATCH',
            body: {
              status
            }
          });
          setDetail(await request(`orders/${detail.order.id}`));
          await load();
        });
      }}>
        <option value="">Change status</option>
        <option>{{
            Pending: 'Confirmed',
            Confirmed: 'Processing',
            Processing: 'Shipped',
            Shipped: 'Delivered'
          }[detail.order.status]}</option>
        <option>Cancelled</option>
      </select>}
    </div>
    <div className="print:hidden">
      <h3 className="font-bold">Status history</h3>
      {detail.history.map(h => <p key={h.id}>{h.created_at} · {h.status} · Staff #{h.user_id}</p>)}
    </div>
  </article>;
}

import { request, Field, input } from '../catalogUi';
export default function StockAdjustmentForm({
  variant,
  setVariant,
  run,
  setHistory,
  load
}) {
  return <form className="card bg-base-100 p-5 gap-3" onSubmit={e => {
    e.preventDefault();
    run(async () => {
      await request(`variants/${variant.id}/adjust`, {
        method: 'POST',
        body: {
          quantity: Number(variant.quantity),
          reason: variant.reason
        }
      });
      setVariant(null);
      setHistory(null);
      await load();
    });
  }}>
    <h2 className="text-xl">Adjust {variant.sku}</h2>
    <Field label="Quantity change (negative removes stock)">
      <input required type="number" className={input} value={variant.quantity} onChange={e => setVariant({
        ...variant,
        quantity: e.target.value
      })} />
    </Field>
    <Field label="Reason">
      <input required className={input} value={variant.reason} onChange={e => setVariant({
        ...variant,
        reason: e.target.value
      })} />
    </Field>
    <button className="btn btn-primary">Save adjustment</button>
  </form>;
}

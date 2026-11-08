import { request, money, Field, input } from '../catalogUi';
export default function ProductEditor({
  detail,
  variant,
  setVariant,
  saveVariant,
  run,
  productDetail
}) {
  return <div className="card bg-base-100 border border-base-300 p-5 space-y-4">
    <h2 className="text-xl font-bold">{detail.product.name}: variants</h2>
    {detail.variants.map(v => <div className="flex flex-wrap gap-3 items-center" key={v.id}>
      <span>{v.sku} · {JSON.stringify(v.attributes)} · {money(v.price_cents)} · Stock {v.stock} · {v.active ? 'Active' : 'Archived'}</span>
      <button className="btn btn-sm" onClick={() => setVariant({
        ...v,
        price: v.price_cents / 100,
        options: Object.entries(v.attributes).map(([k, val]) => `${k}=${val}`).join('\n')
      })}>Edit</button>
    </div>)}
    <button className="btn" onClick={() => setVariant({
      sku: '',
      price: '',
      stock: 0,
      options: '',
      active: true
    })}>Add variant / default SKU</button>
    {variant && <form onSubmit={saveVariant} className="grid gap-3 md:grid-cols-2">
      <Field label="Unique SKU">
        <input required className={input} value={variant.sku} onChange={e => setVariant({
          ...variant,
          sku: e.target.value
        })} />
      </Field>
      <Field label="Price (BDT)">
        <input required type="number" min="0" step="0.01" className={input} value={variant.price} onChange={e => setVariant({
          ...variant,
          price: e.target.value
        })} />
      </Field>
      <Field label="Attributes: one Name=Value per line; blank for default">
        <textarea className="textarea textarea-bordered" value={variant.options} onChange={e => setVariant({
          ...variant,
          options: e.target.value
        })} />
      </Field>
      {!variant.id && <Field label="Opening stock">
        <input type="number" min="0" required className={input} value={variant.stock} onChange={e => setVariant({
          ...variant,
          stock: e.target.value
        })} />
      </Field>}
      <Field label="Active">
        <input type="checkbox" className="checkbox" checked={variant.active} onChange={e => setVariant({
          ...variant,
          active: e.target.checked
        })} />
      </Field>
      <button className="btn btn-primary">Save variant</button>
    </form>}
    <h2 className="text-xl font-bold">Images</h2>
    <form className="flex flex-wrap gap-3" onSubmit={e => {
      e.preventDefault();
      const data = new FormData(e.currentTarget);
      run(async () => {
        await request(`products/${detail.product.id}/images`, {
          method: 'POST',
          body: data
        });
        await productDetail(detail.product.id);
      });
    }}>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required className="file-input file-input-bordered" />
      <input name="position" type="number" min="0" defaultValue="0" className="input input-bordered w-24" aria-label="Position" />
      <select name="primary" className="select select-bordered">
        <option value="0">Additional</option>
        <option value="1">Primary</option>
      </select>
      <button className="btn">Upload (max 5 MB)</button>
    </form>
    <div className="flex flex-wrap gap-4">{detail.images.map(i => <div key={i.id} className="space-y-2">
        <img className="size-32 object-cover rounded" src={i.url} alt={detail.product.name} />
        <p>{i.primary ? 'Primary' : 'Additional'} · Position {i.position}</p>
        <input aria-label="Image position" type="number" min="0" defaultValue={i.position} className="input input-bordered w-24" onBlur={e => {
          const position = Number(e.target.value);
          if (position !== i.position) run(async () => {
            await request(`images/${i.id}`, {
              method: 'PUT',
              body: {
                position,
                primary: i.primary
              }
            });
            await productDetail(detail.product.id);
          });
        }} />
        <button className="btn btn-xs" onClick={() => run(async () => {
          await request(`images/${i.id}`, {
            method: 'PUT',
            body: {
              position: i.position,
              primary: true
            }
          });
          await productDetail(detail.product.id);
        })}>Set primary</button>
        <button className="btn btn-xs btn-error" onClick={() => run(async () => {
          await request(`images/${i.id}`, {
            method: 'DELETE'
          });
          await productDetail(detail.product.id);
        })}>Remove</button>
      </div>)}</div>
  </div>;
}

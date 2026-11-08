import { Field, input } from '../catalogUi';

export function newVariant() {
  return {
    sku: `SKU-${crypto.randomUUID().slice(0, 18).toUpperCase()}`,
    name: '', barcode: '', active: true, options: '',
    retail_price: '', purchase_price: '', dealer_price: '', corporate_price: '',
  };
}

export default function ProductVariantsForm({ variants, change }) {
  const update = (index, field, value) => change('variants', variants.map((variant, row) => (
    row === index ? { ...variant, [field]: value } : variant
  )));

  return <section className="rounded-box border border-base-300 bg-base-100 p-6 space-y-5">
    <div className="flex items-center justify-between gap-3">
      <div><h2 className="text-xl font-bold">SKU &amp; Variants</h2>
        <p className="text-sm text-base-content/60">Every product has at least one SKU. Add rows for more options.</p></div>
      <button type="button" className="btn btn-primary btn-sm" onClick={() => change('variants', [...variants, newVariant()])}>+ Add Variant</button>
    </div>
    {variants.map((variant, index) => <article key={variant.id || variant.clientKey || index} className="rounded-box border border-base-300 bg-base-200/30 p-4 space-y-4">
      <div className="flex items-center justify-between"><h3 className="font-bold">{index === 0 ? 'Primary SKU' : `Variant ${index + 1}`}</h3>
        <button type="button" className="btn btn-sm" disabled={index === 0}
          onClick={() => variant.id ? update(index, 'active', !variant.active) : change('variants', variants.filter((_, row) => row !== index))}>
          {variant.id ? (variant.active ? 'Archive' : 'Restore') : 'Remove'}
        </button></div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Field label="Variant name"><input className={input} value={variant.name} placeholder="e.g. Black / Large" onChange={event => update(index, 'name', event.target.value)} /></Field>
        <Field label="SKU *"><div className="flex"><input required className={input} value={variant.sku} onChange={event => update(index, 'sku', event.target.value)} />
          <button type="button" className="btn" aria-label="Generate SKU" onClick={() => update(index, 'sku', newVariant().sku)}>↻</button></div></Field>
        <Field label="Barcode"><input className={input} value={variant.barcode} onChange={event => update(index, 'barcode', event.target.value)} /></Field>
        <Field label="Status"><select className="select select-bordered w-full" value={variant.active ? 'active' : 'inactive'} onChange={event => update(index, 'active', event.target.value === 'active')}><option value="active">Active</option><option value="inactive">Inactive</option></select></Field>
        {[['purchase_price','Purchase price'],['retail_price','Retail price'],['dealer_price','Dealer price'],['corporate_price','Corporate price']].map(([field,label]) => (
          <Field key={field} label={`${label} (BDT)`}><input className={input} type="number" min="0" max="1000000" step="0.01" value={variant[field]} placeholder="Optional" onChange={event => update(index, field, event.target.value)} /></Field>
        ))}
      </div>
      <Field label="Attributes (optional)"><textarea className="textarea textarea-bordered w-full" value={variant.options} placeholder={'Color=Black\nSize=Large'} onChange={event => update(index, 'options', event.target.value)} /></Field>
      {variant.id && <p className="text-sm">Current stock: {variant.stock}. Adjust stock from Inventory.</p>}
    </article>)}
    <p className="alert alert-info">After saving, add stock for each variant from Inventory using its SKU.</p>
  </section>;
}

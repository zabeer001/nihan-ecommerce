import { Field, input } from '../catalogUi';
import ProductGalleryForm, { readGallery } from './ProductGalleryForm';

export function newVariant() {
  return {
    clientKey: crypto.randomUUID(),
    sku: `SKU-${crypto.randomUUID().slice(0, 18).toUpperCase()}`,
    name: '', barcode: '', active: true, options: '',
    retail_price: '', purchase_price: '', dealer_price: '', corporate_price: '',
    gallery: readGallery(),
  };
}

export default function ProductVariantsForm({ form, change, galleryError }) {
  const { variants, hasVariants, savedWithVariants } = form;
  const update = (index, field, value) => change('variants', variants.map((variant, row) => (
    row === index ? { ...variant, [field]: value } : variant
  )));
  const rows = hasVariants ? variants : variants.slice(0, 1);

  return <section className="rounded-box border border-base-300 bg-base-100 p-6 space-y-5">
    <div className="flex flex-wrap items-end justify-between gap-3">
      <div className="space-y-2">
        <h2 className="text-xl font-bold">SKU &amp; Variants</h2>
        <Field label="Product configuration">
          <select className="select select-bordered w-full" value={hasVariants ? 'variants' : 'simple'}
            onChange={event => change('hasVariants', event.target.value === 'variants')}>
            <option value="simple" disabled={savedWithVariants}>Simple product</option>
            <option value="variants">Product with variants</option>
          </select>
        </Field>
      </div>
      {hasVariants && <button type="button" className="btn btn-primary btn-sm"
        onClick={() => change('variants', [...variants, newVariant()])}>+ Add variant</button>}
    </div>
    {rows.map((variant, index) => <article key={variant.clientKey} className="rounded-box border border-base-300 bg-base-200/30 p-4 space-y-4">
      {hasVariants && <div className="flex items-center justify-between gap-3">
        <h3 className="font-bold">{variant.name.trim() || `Variant ${index + 1}`}</h3>
        <button type="button" className="btn btn-sm" disabled={!variant.id && rows.length === 1}
          onClick={() => variant.id ? update(index, 'active', !variant.active) : change('variants', variants.filter((_, row) => row !== index))}>
          {variant.id ? (variant.active ? 'Archive' : 'Restore') : 'Remove'}
        </button>
      </div>}
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {hasVariants && <Field label="Variant name"><input className={input} value={variant.name} placeholder="e.g. M / Luffy" onChange={event => update(index, 'name', event.target.value)} /></Field>}
        <Field label="SKU *"><div className="flex"><input required className={input} value={variant.sku} onChange={event => update(index, 'sku', event.target.value)} />
          <button type="button" className="btn" aria-label="Generate SKU" onClick={() => update(index, 'sku', newVariant().sku)}>↻</button></div></Field>
        <Field label="Barcode"><input className={input} value={variant.barcode} onChange={event => update(index, 'barcode', event.target.value)} /></Field>
        {hasVariants && <Field label="Status"><select className="select select-bordered w-full" value={variant.active ? 'active' : 'inactive'} onChange={event => update(index, 'active', event.target.value === 'active')}><option value="active">Active</option><option value="inactive">Archived</option></select></Field>}
        {[['purchase_price','Purchase price'],['retail_price','Retail price'],['dealer_price','Dealer price'],['corporate_price','Corporate price']].map(([field,label]) => (
          <Field key={field} label={`${label} (BDT)`}><input className={input} type="number" min="0" max="1000000" step="0.01" value={variant[field]} placeholder="Optional" onChange={event => update(index, field, event.target.value)} /></Field>
        ))}
      </div>
      {hasVariants && <>
        <Field label="Attributes (optional)"><textarea className="textarea textarea-bordered w-full" value={variant.options} placeholder={'Size=M\nDesign=Luffy'} onChange={event => update(index, 'options', event.target.value)} /></Field>
        <ProductGalleryForm title="Variant photos" gallery={variant.gallery}
          error={galleryError(`variants.${index}.gallery`)} change={gallery => update(index, 'gallery', gallery)} />
      </>}
      {variant.id && <p className="text-sm">Current stock: {variant.stock}. Adjust stock from Inventory.</p>}
    </article>)}
  </section>;
}

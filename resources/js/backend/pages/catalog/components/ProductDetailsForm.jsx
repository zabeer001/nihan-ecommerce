import { Field, input } from '../catalogUi';
import CategorySelect from './CategorySelect';
import ProductVariantsForm from './ProductVariantsForm';

export default function ProductDetailsForm({ form, categories, change, save, manageImage, busy }) {
  return <form onSubmit={save} className="space-y-6">
    <section className="rounded-box border border-base-300 bg-base-100 p-6 space-y-5">
      <div><h2 className="text-xl font-bold">Product Information</h2><p className="text-sm text-base-content/60">Basic identity, classification, and inventory settings.</p></div>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label="Product name *"><input required className={input} value={form.name} onChange={event => change('name', event.target.value)} /></Field>
        <Field label="Product code"><div className="flex gap-2"><input className={input} value={form.product_code || ''} placeholder="Generated automatically" onChange={event => change('product_code', event.target.value)} /><button type="button" className="btn" onClick={() => change('product_code', `PRD-${crypto.randomUUID().slice(0,18).toUpperCase()}`)}>Auto Generate</button></div></Field>
        <div className="form-control flex flex-col gap-1">
          <label htmlFor="product-categories" className="text-sm font-medium">Categories *</label>
          <CategorySelect categories={categories} selectedIds={form.category_ids}
            onChange={ids => change('category_ids', ids)} disabled={busy} />
        </div>
        <Field label="Brand"><input className={input} value={form.brand || ''} onChange={event => change('brand', event.target.value)} /></Field>
        <Field label="Model"><input className={input} value={form.model || ''} onChange={event => change('model', event.target.value)} /></Field>
        <Field label="Color"><input className={input} value={form.color || ''} onChange={event => change('color', event.target.value)} /></Field>
        <Field label="Product type"><select className="select select-bordered w-full" value={form.product_type || ''} onChange={event => change('product_type', event.target.value)}><option value="">Not specified</option><option value="physical">Physical</option></select></Field>
        <Field label="Tracking type"><select className="select select-bordered w-full" value={form.tracking_type || ''} onChange={event => change('tracking_type', event.target.value)}><option value="">Not specified</option><option value="quantity">Quantity</option></select></Field>
        <Field label="Unit"><input className={input} value={form.unit || ''} placeholder="e.g. Piece, kg" onChange={event => change('unit', event.target.value)} /></Field>
        <Field label="Status"><select className="select select-bordered w-full" value={form.status} onChange={event => change('status', event.target.value)}><option value="active">Active</option><option value="draft">Draft</option></select></Field>
        <Field label="Slug"><input className={input} value={form.slug} placeholder="Generated from name if empty" onChange={event => change('slug', event.target.value)} /></Field>
      </div>
    </section>
    <ProductVariantsForm variants={form.variants} change={change} />
    <section className="rounded-box border border-base-300 bg-base-100 p-6 space-y-5">
      <h2 className="text-xl font-bold">Description &amp; Images</h2>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label="Description"><textarea className="textarea textarea-bordered min-h-40 w-full" value={form.description || ''} onChange={event => change('description', event.target.value)} /></Field>
        <div className="space-y-4">
          <Field label="Featured image"><input className="file-input file-input-bordered w-full" type="file" accept="image/jpeg,image/png,image/webp" onChange={event => change('featuredImage', event.target.files[0] || null)} /></Field>
          <Field label="Additional images"><input className="file-input file-input-bordered w-full" type="file" multiple accept="image/jpeg,image/png,image/webp" onChange={event => change('additionalImages', Array.from(event.target.files))} /></Field>
          <p className="text-xs text-base-content/60">JPG, PNG or WebP; up to 5 MB per image.</p>
        </div>
      </div>
      <div className="flex flex-wrap gap-4">{form.images.map(image => <article key={image.id} className="space-y-2">
        <img src={image.url} alt={form.name} className="size-28 rounded object-cover" />
        <p>{image.primary ? 'Featured' : 'Gallery'} · Position {image.position}</p>
        <label className="text-sm">Position <input aria-label="Image position" type="number" min="0" className="input input-bordered w-20" defaultValue={image.position} onBlur={event => { const position = Number(event.target.value); if (position !== image.position) manageImage(image, 'position', position); }} /></label>
        {!image.primary && <button type="button" className="btn btn-xs" onClick={() => manageImage(image, 'primary')}>Set featured</button>}
        <button type="button" className="btn btn-xs btn-error" onClick={() => manageImage(image, 'delete')}>Remove</button>
      </article>)}</div>
    </section>
    <div className="flex justify-end gap-3 rounded-box border border-base-300 bg-base-100 p-5">
      <a href="/dashboard/products" className="btn">Cancel</a>
      <button className="btn btn-primary">{form.id ? 'Save product' : 'Create product'}</button>
    </div>
  </form>;
}

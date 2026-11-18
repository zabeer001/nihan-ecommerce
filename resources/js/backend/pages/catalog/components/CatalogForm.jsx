import { Field, input } from '../catalogUi';
import CategoryUsageGuideForm from './CategoryUsageGuideForm';
export default function CatalogForm({
  form,
  section,
  categories,
  save,
  change,
  setForm
}) {
  return <form onSubmit={save} className="card bg-base-100 border border-base-300 p-5 grid gap-4 md:grid-cols-2">
    <Field label="Name">
      <input required className={input} value={form.name} onChange={e => change('name', e.target.value)} />
    </Field>
    {section === 'categories' ? <>
<Field label="Active">
        <input type="checkbox" className="checkbox" checked={form.active} onChange={e => change('active', e.target.checked)} />
      </Field>
<div className="md:col-span-2 space-y-4">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-lg font-semibold">Content cards</h2>
          <button type="button" className="btn btn-sm" disabled={(form.content_cards || []).length >= 4}
            onClick={() => change('content_cards', [...(form.content_cards || []), { label: '', title: '', description: '', color: '#c9915a' }])}>Add card</button>
        </div>
        <p className="text-sm opacity-70">Add up to four cards. Cards appear in this order. Remove all cards to hide the section.</p>
        {(form.content_cards || []).map((card, index) => <fieldset key={index} className="rounded-box border border-base-300 p-4 grid gap-4 md:grid-cols-2">
          <legend className="px-2 font-semibold">Card {index + 1}</legend>
          <Field label="Label">
            <input className={input} required maxLength={80} value={card.label || ''}
              onChange={e => change('content_cards', form.content_cards.map((item, i) => i === index ? { ...item, label: e.target.value } : item))} />
          </Field>
          <Field label="Title">
            <input className={input} required maxLength={150} value={card.title || ''}
              onChange={e => change('content_cards', form.content_cards.map((item, i) => i === index ? { ...item, title: e.target.value } : item))} />
          </Field>
          <div className="md:col-span-2"><Field label="Description">
            <textarea className="textarea textarea-bordered w-full" required maxLength={500} rows={3} value={card.description || ''}
              onChange={e => change('content_cards', form.content_cards.map((item, i) => i === index ? { ...item, description: e.target.value } : item))} />
          </Field></div>
          <Field label="Color">
            <input type="color" className="h-10 w-20 cursor-pointer" value={card.color || '#c9915a'}
              onChange={e => change('content_cards', form.content_cards.map((item, i) => i === index ? { ...item, color: e.target.value } : item))} />
          </Field>
          <div className="flex flex-wrap items-end gap-2">
            <button type="button" className="btn btn-sm" disabled={index === 0} onClick={() => {
              const cards = [...form.content_cards];
              [cards[index - 1], cards[index]] = [cards[index], cards[index - 1]];
              change('content_cards', cards);
            }}>Move up</button>
            <button type="button" className="btn btn-sm" disabled={index === form.content_cards.length - 1} onClick={() => {
              const cards = [...form.content_cards];
              [cards[index], cards[index + 1]] = [cards[index + 1], cards[index]];
              change('content_cards', cards);
            }}>Move down</button>
            <button type="button" className="btn btn-sm btn-error btn-outline" onClick={() => change('content_cards', form.content_cards.filter((_, i) => i !== index))}>Remove card</button>
          </div>
        </fieldset>)}
      </div>
<CategoryUsageGuideForm value={form.usage_guide} onChange={guide => change('usage_guide', guide)} />
</> : <>
<Field label="Slug">
        <input required className={input} value={form.slug} onChange={e => change('slug', e.target.value)} />
      </Field>
<Field label="Categories">
        <select required multiple className="select select-bordered h-36" value={(form.category_ids || []).map(String)}
          onChange={event => change('category_ids', Array.from(event.target.selectedOptions, option => Number(option.value)))}>
          {categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}
        </select>
        <span className="text-xs text-base-content/60">Choose one or more categories. Hold Ctrl (Windows/Linux) or Command (Mac) to select several.</span>
      </Field>
<Field label="Status">
        <select className="select select-bordered" value={form.status} onChange={e => change('status', e.target.value)}>
          <option>draft</option>
          <option>active</option>
        </select>
      </Field>
{!form.id && <>
        <Field label="SKU (optional)">
          <input className={input} value={form.sku || ''} placeholder="Generated automatically if empty"
            onChange={event => change('sku', event.target.value)} />
        </Field>
        <Field label="Price (BDT)">
          <input required type="number" min="0" step="0.01" className={input} value={form.price || '0'}
            onChange={event => change('price', event.target.value)} />
        </Field>
        <Field label="Opening stock">
          <input required type="number" min="0" step="1" className={input} value={form.opening_stock ?? 0}
            onChange={event => change('opening_stock', Number(event.target.value))} />
        </Field>
        <p className="text-sm text-base-content/60">A default variant is created automatically. You can add size or color variants later.</p>
      </>}
      <Field label="Featured image">
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="file-input file-input-bordered w-full"
          onChange={event => change('featuredImage', event.target.files[0] || null)}
        />
        <span className="text-xs text-base-content/60">Main product image. JPG, PNG or WebP, up to 5 MB.</span>
      </Field>
      <Field label="Additional images">
        <input
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp"
          className="file-input file-input-bordered w-full"
          onChange={event => change('additionalImages', Array.from(event.target.files))}
        />
        <span className="text-xs text-base-content/60">Select multiple gallery images, up to 5 MB each. Existing images stay unless removed under Variants &amp; images.</span>
      </Field>
      <Field label="Description">
        <textarea className="textarea textarea-bordered" value={form.description || ''} onChange={e => change('description', e.target.value)} />
      </Field>
</>}
    <div className="flex gap-2">
      <button className="btn btn-primary">Save</button>
      <button type="button" className="btn" onClick={() => setForm(null)}>Close</button>
    </div>
  </form>;
}

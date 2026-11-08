import { useEffect, useState } from 'react';
import useAuth from '../../../shared/hooks/useAuth';
import { request, cents } from './catalogUi';
import ProductDetailsForm from './components/ProductDetailsForm';
import { newVariant } from './components/ProductVariantsForm';

export default function ProductFormPage({ productId = null }) {
  const { role } = useAuth();
  const [form, setForm] = useState(null);
  const [categories, setCategories] = useState([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const allowed = ['admin', 'superadmin'].includes(role);
  const section = 'products';
  const readVariant = variant => ({
    ...variant,
    name: variant.name || '', barcode: variant.barcode || '',
    options: Object.entries(variant.attributes || {}).map(([key, value]) => `${key}=${value}`).join('\n'),
    retail_price: variant.price_cents / 100,
    purchase_price: variant.purchase_price_cents == null ? '' : variant.purchase_price_cents / 100,
    dealer_price: variant.dealer_price_cents == null ? '' : variant.dealer_price_cents / 100,
    corporate_price: variant.corporate_price_cents == null ? '' : variant.corporate_price_cents / 100,
  });

  useEffect(() => {
    if (!allowed) return;
    let active = true;
    setBusy(true);
    Promise.all([
      request('categories'),
      productId ? request(`products/${productId}`) : Promise.resolve(null),
    ]).then(([categoryList, details]) => {
      if (!active) return;
      setCategories(categoryList);
      setForm(details ? {
        ...details.product, category_ids: details.product.categories.map(category => category.id),
        variants: details.variants.length ? details.variants.map(readVariant) : [newVariant()],
        images: details.images,
      } : {
        name: '', slug: '', description: '', category_ids: [], status: 'active',
        product_code: '', brand: '', model: '', color: '', unit: '', product_type: 'physical', tracking_type: 'quantity',
        variants: [newVariant()], images: [],
      });
    }).catch(error => { if (active) setError(error.message); })
      .finally(() => { if (active) setBusy(false); });
    return () => { active = false; };
  }, [productId, allowed]);

  const change = (key, value) => setForm(current => ({ ...current, [key]: value }));
  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const { id, featuredImage, additionalImages = [], ...payload } = form;
      const uploads = [
        ...(featuredImage ? [{ file: featuredImage, primary: true }] : []),
        ...additionalImages.map(file => ({ file, primary: false })),
      ];

      for (const { file } of uploads) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
          throw new Error('Choose JPG, PNG or WebP images no larger than 5 MB each.');
        }
      }

      payload.slug = payload.slug || payload.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || `product-${crypto.randomUUID()}`;
      payload.variants = form.variants.map((variant, index) => {
        const attributes = {};
        for (const line of variant.options.split('\n').filter(line => line.trim())) {
          const separator = line.indexOf('=');
          if (separator < 1) throw new Error(`Variant ${index + 1}: use Attribute=Value, one per line.`);
          const key = line.slice(0, separator).trim().toLowerCase();
          if (key in attributes) throw new Error(`Variant ${index + 1}: duplicate attribute name.`);
          attributes[key] = line.slice(separator + 1).trim();
        }
        if (!Object.keys(attributes).length) {
          if (variant.name.trim()) attributes.option = variant.name.trim();
          else if (index > 0) attributes.option = variant.sku;
        }
        const optionalPrice = field => variant[field] === '' || variant[field] == null ? null : cents(variant[field]);
        return {
          ...(variant.id ? { id: variant.id } : {}),
          sku: variant.sku, name: variant.name || null, barcode: variant.barcode || null,
          attributes, active: variant.active,
          price_cents: cents(variant.retail_price),
          purchase_price_cents: optionalPrice('purchase_price'),
          dealer_price_cents: optionalPrice('dealer_price'),
          corporate_price_cents: optionalPrice('corporate_price'),
        };
      });
      delete payload.images;
      delete payload.categories;
      const record = await request(section + (id ? '/' + id : ''), {
        method: id ? 'PUT' : 'POST',
        body: payload,
      });

      // Keep the saved ID if an upload fails, so retrying cannot create another product.
      setForm(current => ({ ...current, id: record.id, product_code: record.product_code,
        variants: current.variants.map(variant => ({ ...variant, id: record.variants.find(saved => saved.sku === variant.sku)?.id })),
      }));
      if (section === 'products' && uploads.length) {
        const existing = await request(`products/${record.id}`);
        const nextPosition = existing.images.reduce((next, image) => Math.max(next, image.position + 1), 0);
        for (const [index, upload] of uploads.entries()) {
          const imageData = new FormData();
          imageData.append('image', upload.file);
          imageData.append('primary', upload.primary ? '1' : '0');
          imageData.append('position', String(nextPosition + index));
          await request(`products/${record.id}/images`, { method: 'POST', body: imageData });
          // Remove successful uploads from pending state to avoid duplicates on retry.
          setForm(current => ({
            ...current,
            featuredImage: upload.primary ? null : current.featuredImage,
            additionalImages: (current.additionalImages || []).filter(file => file !== upload.file),
          }));
        }
      }

      window.location.assign('/dashboard/products');
    } catch (error) {
      setError(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function manageImage(image, action, position = image.position) {
    setBusy(true);
    setError('');
    try {
      await request(`images/${image.id}`, action === 'delete' ? { method: 'DELETE' } : {
        method: 'PUT', body: { position, primary: action === 'primary' || image.primary },
      });
      const details = await request(`products/${form.id}`);
      setForm(current => ({ ...current, images: details.images }));
    } catch (error) { setError(error.message); }
    finally { setBusy(false); }
  }


  if (!allowed) return <p className="alert alert-error">Administrator access required.</p>;
  return <section className="space-y-5">
    <div className="flex items-center justify-between gap-3">
      <h1 className="text-3xl font-bold">{productId ? 'Edit product' : 'Add product'}</h1>
      <a href="/dashboard/products" className="btn">Back to products</a>
    </div>
    {error && <p role="alert" className="alert alert-error">{error}</p>}
    <fieldset disabled={busy}>
      {form && <ProductDetailsForm form={form} categories={categories}
        save={save} change={change} manageImage={manageImage} busy={busy} />}
    </fieldset>
    {busy && <p role="status">Loading / saving…</p>}
  </section>;
}

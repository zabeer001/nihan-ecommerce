import { useEffect, useRef, useState } from 'react';
import { usePermissions } from '../../layouts/PermissionsContext';
import { request, cents } from './catalogUi';
import ProductDetailsForm from './components/ProductDetailsForm';
import { readGallery } from './components/ProductGalleryForm';
import { newVariant } from './components/ProductVariantsForm';

function readVariant(variant) {
  const { images, ...details } = variant;
  return {
    ...details,
    clientKey: `variant-${variant.id}`,
    gallery: readGallery(images),
    name: variant.name || '', barcode: variant.barcode || '',
    options: Object.entries(variant.attributes || {}).map(([key, value]) => `${key}=${value}`).join('\n'),
    retail_price: variant.price_cents / 100,
    purchase_price: variant.purchase_price_cents == null ? '' : variant.purchase_price_cents / 100,
    dealer_price: variant.dealer_price_cents == null ? '' : variant.dealer_price_cents / 100,
    corporate_price: variant.corporate_price_cents == null ? '' : variant.corporate_price_cents / 100,
  };
}

function variantPayload(variant, index, hasVariants) {
  const attributes = Object.create(null);
  if (hasVariants) {
    for (const line of variant.options.split('\n').filter(line => line.trim())) {
      const separator = line.indexOf('=');
      const key = line.slice(0, separator).trim().toLowerCase();
      const value = line.slice(separator + 1).trim();
      if (separator < 1 || !key || !value) throw new Error(`Variant ${index + 1}: use Attribute=Value, one per line.`);
      if (Object.hasOwn(attributes, key)) throw new Error(`Variant ${index + 1}: duplicate attribute name.`);
      attributes[key] = value;
    }
    if (!Object.keys(attributes).length) {
      if (variant.name.trim()) attributes.option = variant.name.trim();
      else if (index > 0) attributes.option = variant.sku;
    }
  }
  const optionalPrice = field => variant[field] === '' || variant[field] == null ? null : cents(variant[field]);
  return {
    ...(variant.id ? { id: variant.id } : {}),
    sku: variant.sku, name: hasVariants ? variant.name.trim() || null : null, barcode: variant.barcode || null,
    attributes, active: !hasVariants || variant.active,
    price_cents: cents(variant.retail_price),
    purchase_price_cents: optionalPrice('purchase_price'),
    dealer_price_cents: optionalPrice('dealer_price'),
    corporate_price_cents: optionalPrice('corporate_price'),
  };
}

export default function ProductFormPage({ productId = null }) {
  const { can, loading } = usePermissions();
  const [form, setForm] = useState(null);
  const [categories, setCategories] = useState([]);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const stagedUploads = useRef(new WeakMap());
  const allowed = can(productId ? 'products.update' : 'products.create');

  useEffect(() => {
    if (!allowed) return;
    let active = true;
    setBusy(true);
    setForm(null);
    setError('');
    setFieldErrors({});
    stagedUploads.current = new WeakMap();
    Promise.all([
      request('categories'),
      productId ? request(`products/${productId}`) : Promise.resolve(null),
    ]).then(([categoryList, details]) => {
      if (!active) return;
      setCategories(categoryList);
      const variants = details?.variants.map(readVariant) || [];
      const hasVariants = variants.length > 1 || variants.some(variant => (
        variant.name || variant.options || variant.gallery.items.length
      ));
      setForm(details ? {
        ...details.product,
        category_ids: details.product.categories.map(category => category.id),
        variants: variants.length ? variants : [newVariant()],
        gallery: readGallery(details.images),
        hasVariants,
        savedWithVariants: hasVariants,
      } : {
        name: '', slug: '', description: '', category_ids: [], status: 'active',
        product_code: '', brand: '', model: '', color: '', unit: '', product_type: 'physical', tracking_type: 'quantity',
        variants: [newVariant()], gallery: readGallery(), hasVariants: false, savedWithVariants: false,
      });
    }).catch(error => { if (active) setError(error.message); })
      .finally(() => { if (active) setBusy(false); });
    return () => { active = false; };
  }, [productId, allowed]);

  const change = (key, value) => {
    setForm(current => ({ ...current, [key]: value }));
    setFieldErrors({});
  };
  const galleryError = field => Object.entries(fieldErrors)
    .find(([key]) => key === field || key.startsWith(`${field}.`))?.[1]?.[0];

  async function stageGallery(gallery, field) {
    const items = [];
    const path = productId ? `products/${productId}/image-uploads` : 'products/image-uploads';
    for (const photo of gallery.items) {
      if (!photo.file) {
        items.push({ id: photo.id, primary: photo.primary });
        continue;
      }

      let upload = stagedUploads.current.get(photo.file);
      if (!upload || Date.parse(upload.expires_at) <= Date.now()) {
        const body = new FormData();
        body.append('image', photo.file);
        try {
          upload = await request(path, { method: 'POST', body });
        } catch (error) {
          error.validationErrors = { [field]: [`${photo.file.name}: ${error.message}`] };
          throw error;
        }
        stagedUploads.current.set(photo.file, upload);
      }
      items.push({ upload_id: upload.upload_id, primary: photo.primary });
    }

    return { items, removed_ids: gallery.removed_ids };
  }

  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setError('');
    setFieldErrors({});
    try {
      const { id, categories, gallery, variants, hasVariants, savedWithVariants, ...payload } = form;
      const rows = hasVariants ? variants : variants.slice(0, 1);
      payload.slug = payload.slug || payload.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || `product-${crypto.randomUUID()}`;
      payload.variants = rows.map((variant, index) => variantPayload(variant, index, hasVariants));
      if (hasVariants && rows.length === 1 && !Object.keys(payload.variants[0].attributes).length) {
        throw new Error('Give the variant a name or add an attribute.');
      }

      const errors = {};
      const galleries = [{ field: 'gallery', gallery, target: payload }];
      if (hasVariants) {
        rows.forEach((variant, index) => {
          const field = `variants.${index}.gallery`;
          const photos = variant.gallery.items;
          if (payload.status === 'active' && variant.active && (!photos.some(photo => photo.primary) || !photos.some(photo => !photo.primary))) {
            errors[field] = ['Add a featured image and at least one gallery image.'];
          }
          galleries.push({ field, gallery: variant.gallery, target: payload.variants[index] });
        });
      }
      for (const { field, gallery } of galleries) {
        const invalid = gallery.items.find(({ file }) => file && (
          !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024
        ));
        if (invalid) errors[field] = [`${invalid.file.name}: choose a JPG, PNG or WebP image up to 5 MB.`];
      }
      if (Object.keys(errors).length) {
        setFieldErrors(errors);
        setError('Check the highlighted photo galleries.');
        return;
      }
      for (const { field, gallery, target } of galleries) {
        if (gallery.dirty) target.gallery = await stageGallery(gallery, field);
      }

      await request(id ? `products/${id}` : 'products', { method: id ? 'PUT' : 'POST', body: payload });
      window.location.assign('/dashboard/products');
    } catch (error) {
      setError(error.message);
      setFieldErrors(error.validationErrors || {});
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <p role="status">Loading permissions…</p>;
  if (!allowed) return <p className="alert alert-error">You do not have access to this page.</p>;
  return <section className="space-y-5">
    <div className="flex items-center justify-between gap-3">
      <h1 className="text-3xl font-bold">{productId ? 'Edit product' : 'Add product'}</h1>
      <a href="/dashboard/products" className="btn">Back to products</a>
    </div>
    {error && <p role="alert" className="alert alert-error">{error}</p>}
    <fieldset disabled={busy}>
      {form && <ProductDetailsForm form={form} categories={categories}
        save={save} change={change} galleryError={galleryError} busy={busy} />}
    </fieldset>
    {busy && <p role="status">Loading / saving…</p>}
  </section>;
}

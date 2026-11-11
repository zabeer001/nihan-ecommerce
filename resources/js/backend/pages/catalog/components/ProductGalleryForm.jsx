import { useEffect, useState } from 'react';
import { Field } from '../catalogUi';

const acceptedImages = 'image/jpeg,image/png,image/webp';

export function readGallery(images = []) {
  return { items: images, removed_ids: [], dirty: false };
}

function PhotoPreview({ photo, alt }) {
  const [preview, setPreview] = useState('');

  useEffect(() => {
    if (!photo.file) return;
    const url = URL.createObjectURL(photo.file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [photo.file]);

  const url = photo.url || preview;
  return url ? <img src={url} alt={alt} className="size-28 rounded object-cover" /> : null;
}

export default function ProductGalleryForm({ gallery, change, title, error }) {
  const featured = gallery.items.find(photo => photo.primary);
  const photos = gallery.items.filter(photo => !photo.primary);

  function update(items, removed = null) {
    change({
      items,
      removed_ids: removed?.id ? [...gallery.removed_ids, removed.id] : gallery.removed_ids,
      dirty: true,
    });
  }

  function move(index, offset) {
    const reordered = [...photos];
    const destination = index + offset;
    [reordered[index], reordered[destination]] = [reordered[destination], reordered[index]];
    update(featured ? [featured, ...reordered] : reordered);
  }

  function select(event, primary) {
    const files = Array.from(event.target.files);
    event.target.value = '';
    if (!files.length) return;
    const added = files.map(file => ({ file, primary, key: crypto.randomUUID() }));
    update(primary ? [...added, ...photos] : [...gallery.items, ...added], primary ? featured : null);
  }

  function renderPhoto(photo, index) {
    const label = photo.primary ? 'Featured image' : `Gallery image ${index + 1}`;
    return <li key={photo.id || photo.key} className="space-y-2">
      <PhotoPreview photo={photo} alt={label} />
      <p className="text-xs">{label}</p>
      <div className="flex gap-1">
        {!photo.primary && <>
          <button type="button" className="btn btn-xs" disabled={index === 0}
            aria-label={`Move ${label} earlier`} onClick={() => move(index, -1)}>↑</button>
          <button type="button" className="btn btn-xs" disabled={index === photos.length - 1}
            aria-label={`Move ${label} later`} onClick={() => move(index, 1)}>↓</button>
          <button type="button" className="btn btn-xs" onClick={() =>
            update(gallery.items.map(item => ({ ...item, primary: item === photo })))
          }>Set featured</button>
        </>}
        <button type="button" className="btn btn-xs btn-error" aria-label={`Remove ${label}`}
          onClick={() => update(gallery.items.filter(item => item !== photo), photo)}>Remove</button>
      </div>
    </li>;
  }

  return <section className="space-y-3">
    <h3 className="font-semibold">{title}</h3>
    <div className="grid gap-3 md:grid-cols-2">
      <Field label={featured ? 'Replace featured image' : 'Featured image'}>
        <input className="file-input file-input-bordered w-full" type="file" accept={acceptedImages}
          onChange={event => select(event, true)} />
      </Field>
      <Field label="Add gallery images">
        <input className="file-input file-input-bordered w-full" type="file" multiple accept={acceptedImages}
          onChange={event => select(event, false)} />
      </Field>
    </div>
    <p className="text-xs text-base-content/60">JPG, PNG or WebP. Up to 5 MB per image.</p>
    {error && <p role="alert" className="text-sm text-error">{error}</p>}
    <ul className="flex flex-wrap gap-4">
      {featured && renderPhoto(featured, -1)}
      {photos.map(renderPhoto)}
    </ul>
  </section>;
}

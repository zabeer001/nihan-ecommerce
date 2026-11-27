import { useEffect, useRef } from 'react';

export default function DeleteCatalogModal({ item, kind = 'product', busy, error, onClose, onConfirm }) {
  const dialogRef = useRef(null);
  useEffect(() => {
    const dialog = dialogRef.current;
    dialog.showModal();
    return () => dialog.close();
  }, []);

  return <dialog ref={dialogRef} className="modal" aria-labelledby="delete-catalog-title"
    aria-describedby="delete-catalog-description" onCancel={event => {
      event.preventDefault();
      if (!busy) onClose();
    }}>
    <div className="modal-box space-y-4">
      <h2 id="delete-catalog-title" className="text-xl font-bold">Delete {kind}?</h2>
      <p id="delete-catalog-description">Delete <strong>{item.name}</strong>? {kind === 'product' ? 'It will be removed from the product list and storefront. Existing orders and stock history will be preserved.' : 'It will be removed from the category list and storefront. Categories assigned to products cannot be deleted.'}</p>
      {error && <p role="alert" className="alert alert-error">{error}</p>}
      <div className="modal-action">
        <button type="button" className="btn" autoFocus disabled={busy} onClick={onClose}>Cancel</button>
        <button type="button" className="btn btn-error" disabled={busy} onClick={onConfirm}>{busy ? 'Deleting…' : `Delete ${kind}`}</button>
      </div>
    </div>
  </dialog>;
}

import { useEffect, useRef } from 'react';
import CatalogForm from './CatalogForm';

export default function CategoryModal({ form, save, change, setForm, busy, error }) {
  const dialogRef = useRef(null);
  const close = () => {
    if (!busy) setForm(null);
  };

  useEffect(() => {
    const dialog = dialogRef.current;
    dialog.showModal();
    return () => dialog.close();
  }, []);

  return (
    <dialog
      ref={dialogRef}
      className="modal"
      aria-labelledby="category-modal-title"
      onCancel={event => {
        event.preventDefault();
        close();
      }}
      onClick={event => {
        if (event.target === event.currentTarget) close();
      }}
    >
      <div className="modal-box w-11/12 max-w-2xl bg-base-100 text-base-content">
        <div className="mb-4 flex items-center justify-between gap-3">
          <h2 id="category-modal-title" className="text-2xl font-bold">
            {form.id ? 'Edit category' : 'Add category'}
          </h2>
          <button type="button" className="btn btn-sm btn-circle" aria-label="Close popup" disabled={busy} onClick={close}>
            ✕
          </button>
        </div>
        {error && <p role="alert" className="alert alert-error mb-4">{error}</p>}
        <fieldset disabled={busy}>
          <CatalogForm form={form} section="categories"
            save={save} change={change} setForm={close} />
        </fieldset>
        {busy && <p role="status" className="mt-3">Saving…</p>}
      </div>
    </dialog>
  );
}

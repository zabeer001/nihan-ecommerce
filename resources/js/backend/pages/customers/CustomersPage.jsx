import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { usePermissions } from '../../layouts/PermissionsContext';
import { Field, input, request } from '../catalog/catalogUi';

const emptyCustomer = { name: '', phone: '', email: '', address: '' };

export default function CustomersPage() {
    const { can, loading } = usePermissions();
    const canView = can('customers.view');
    const canCreate = can('customers.create');
    const queryClient = useQueryClient();
    const [form, setForm] = useState(null);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const customers = useQuery({
        queryKey: ['customers', page, search],
        queryFn: () => request(`customers?${new URLSearchParams({ page, search })}`),
        enabled: canView,
    });
    const mutation = useMutation({
        mutationFn: async ({ id, method, body, message }) => {
            await request(id ? `customers/${id}` : 'customers', { method, body });
            return message;
        },
        onSuccess: async (_, { method }) => {
            if (method !== 'DELETE') setForm(null);
            if (method === 'DELETE' && customers.data?.data.length === 1 && page > 1) {
                setPage(page - 1);
            }
            await queryClient.invalidateQueries({ queryKey: ['customers'] });
        },
    });

    function openForm(customer) {
        mutation.reset();
        setForm(customer);
    }

    function save(event) {
        event.preventDefault();
        const { id, name, phone, email, address } = form;
        mutation.mutate({
            id, method: id ? 'PUT' : 'POST', body: { name, phone, email, address },
            message: id ? 'Customer updated.' : 'Customer added.',
        });
    }

    if (loading) return <p role="status">Loading permissions…</p>;
    if (!canView && !canCreate) return <p className="alert alert-error">You do not have access to this page.</p>;

    return <section className="space-y-5">
        <h1 className="text-3xl font-bold">Customers</h1>
        {(customers.error || mutation.error) && <p role="alert" className="alert alert-error">
            {(mutation.error || customers.error).message}
        </p>}
        {mutation.isSuccess && <p role="status" className="alert alert-success">{mutation.data}</p>}
        <fieldset disabled={mutation.isPending} className="rounded-box bg-base-100 p-5 space-y-4">
            <div className="flex flex-wrap gap-2">
                {canView && <input type="search" aria-label="Search customers" placeholder="Search name, phone, or email"
                    className="input input-bordered w-full sm:w-72" value={search} maxLength={255}
                    onChange={event => { setSearch(event.target.value); setPage(1); }} />}
                {canCreate && <button type="button" className="btn btn-primary"
                    onClick={() => openForm(emptyCustomer)}>Add customer</button>}
            </div>

            {form && <form onSubmit={save} className="space-y-4 rounded-box border border-base-300 p-4">
                <h2 className="text-xl font-bold">{form.id ? 'Edit customer' : 'Add customer'}</h2>
                <div className="grid gap-3 sm:grid-cols-2">
                    {[['name', 'Name', 'text'], ['phone', 'Phone', 'tel'], ['email', 'Email', 'email']].map(([key, label, type]) =>
                        <Field key={key} label={label}>
                            <input className={input} type={type} required={key !== 'email'} maxLength={255}
                                value={form[key] ?? ''} onChange={event => setForm({ ...form, [key]: event.target.value })} />
                        </Field>
                    )}
                    <Field label="Address">
                        <textarea className="textarea textarea-bordered w-full" maxLength={5000}
                            value={form.address ?? ''} onChange={event => setForm({ ...form, address: event.target.value })} />
                    </Field>
                </div>
                <div className="flex gap-2">
                    <button className="btn btn-primary">Save customer</button>
                    <button type="button" className="btn" onClick={() => openForm(null)}>Cancel</button>
                </div>
            </form>}

            {canView && customers.isPending && <p role="status">Loading customers…</p>}
            {canView && customers.data && <>
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Actions</th></tr></thead>
                        <tbody>
                            {customers.data.data.length === 0 && <tr><td colSpan={5}>No customers found.</td></tr>}
                            {customers.data.data.map(customer => <tr key={customer.id}>
                                <th scope="row">{customer.name}</th>
                                <td>{customer.phone}</td>
                                <td>{customer.email || '—'}</td>
                                <td className="whitespace-pre-line">{customer.address || '—'}</td>
                                <td><div className="flex gap-2">
                                    {can('customers.update') && <button type="button" className="btn btn-sm"
                                        onClick={() => openForm(customer)}>Edit</button>}
                                    {can('customers.delete') && <button type="button" className="btn btn-sm btn-outline btn-error"
                                        onClick={() => {
                                            if (!window.confirm(`Delete customer “${customer.name}”?`)) return;
                                            if (form?.id === customer.id) setForm(null);
                                            mutation.mutate({ id: customer.id, method: 'DELETE', message: 'Customer deleted.' });
                                        }}>Delete</button>}
                                </div></td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
                {customers.data.last_page > 1 && <div className="flex items-center gap-3">
                    <button type="button" className="btn btn-sm" disabled={page === 1} onClick={() => setPage(page - 1)}>Previous</button>
                    <span>Page {page} of {customers.data.last_page}</span>
                    <button type="button" className="btn btn-sm" disabled={page >= customers.data.last_page}
                        onClick={() => setPage(page + 1)}>Next</button>
                </div>}
            </>}
        </fieldset>
    </section>;
}

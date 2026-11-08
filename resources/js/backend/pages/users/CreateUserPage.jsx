import React, { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import useAuth from '../../../shared/hooks/useAuth';
import { createUser } from './api/userApi';

function CreateUserPage() {
    const { role: userRole } = useAuth();
    const canAccessUsers = ['admin', 'superadmin'].includes(userRole.toLowerCase());
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        name: '',
        email: '',
        role: 'user',
        is_frozen: false,
        password: '',
        password_confirmation: '',
    });

    const createUserMutation = useMutation({
        mutationFn: () => createUser(form),
        onSuccess: () => {
            window.location.href = '/dashboard/users?created=1';
        },
        onError: (err) => {
            setError(err.message || 'Failed to create user.');
        },
    });

    const onFieldChange = (event) => {
        const { name, type, value, checked } = event.target;

        setForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const onSubmit = async (event) => {
        event.preventDefault();
        setError('');
        createUserMutation.mutate();
    };

    if (!canAccessUsers) {
        window.location.replace('/dashboard/unauthorized');
        return null;
    }

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Add User</h1>
                            <p className="mt-2 text-sm text-base-content/70">
                                Create a new platform user. Signed in as:{' '}
                                <span className="font-semibold uppercase">{userRole}</span>
                            </p>
                        </div>
                        <Link href="/dashboard/users" className="btn btn-outline">
                            Back
                        </Link>
                    </div>
                </div>
            </section>

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {error ? <div className="alert alert-error mb-4 py-2 text-sm">{error}</div> : null}

                    <form className="grid gap-4 md:grid-cols-2" onSubmit={onSubmit} autoComplete="off">
                    <label className="form-control w-full">
                        <span className="label-text mb-2">Name</span>
                        <input
                            type="text"
                            name="name"
                            className="input input-bordered w-full"
                            value={form.name}
                            onChange={onFieldChange}
                            required
                        />
                    </label>

                    <label className="form-control w-full">
                        <span className="label-text mb-2">Email</span>
                        <input
                            type="email"
                            name="email"
                            className="input input-bordered w-full"
                            value={form.email}
                            onChange={onFieldChange}
                            required
                        />
                    </label>

                    <label className="form-control w-full">
                        <span className="label-text mb-2">Role</span>
                        <select
                            name="role"
                            className="select select-bordered w-full"
                            value={form.role}
                            onChange={onFieldChange}
                        >
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                            <option value="editor">Editor</option>
                            <option value="user">User</option>
                        </select>
                    </label>

                    <label className="form-control w-full">
                        <span className="label-text mb-2">Account Status</span>
                        <label className="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 px-4 py-3">
                            <input
                                type="checkbox"
                                name="is_frozen"
                                className="toggle toggle-error"
                                checked={Boolean(form.is_frozen)}
                                onChange={onFieldChange}
                            />
                            <span className="label-text">
                                {form.is_frozen ? 'Frozen account' : 'Active account'}
                            </span>
                        </label>
                    </label>

                    <label className="form-control w-full">
                        <span className="label-text mb-2">Password</span>
                        <input
                            type="password"
                            name="password"
                            className="input input-bordered w-full"
                            value={form.password}
                            onChange={onFieldChange}
                            required
                        />
                    </label>

                    <label className="form-control w-full">
                        <span className="label-text mb-2">Confirm Password</span>
                        <input
                            type="password"
                            name="password_confirmation"
                            className="input input-bordered w-full"
                            value={form.password_confirmation}
                            onChange={onFieldChange}
                            required
                        />
                    </label>

                        <div className="md:col-span-2">
                            <button type="submit" className="btn btn-success" disabled={createUserMutation.isPending}>
                                {createUserMutation.isPending ? 'Creating...' : 'Create User'}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    );
}

export default CreateUserPage;

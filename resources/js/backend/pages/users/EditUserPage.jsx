import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useMutation, useQuery } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import useAuth from '../../../shared/hooks/useAuth';
import { fetchUser, updateUser } from './api/userApi';

function EditUserPage() {
    const { userId } = usePage().props;
    const { role: userRole } = useAuth();
    const canAccessUsers = ['admin', 'superadmin'].includes(userRole.toLowerCase());
    const [user, setUser] = useState(null);
    const [error, setError] = useState('');
    const [form, setForm] = useState({
        name: '',
        email: '',
        role: 'user',
        is_frozen: false,
        password: '',
        password_confirmation: '',
    });

    const userQuery = useQuery({
        queryKey: ['backend-user', userId],
        queryFn: () => fetchUser(userId),
        enabled: canAccessUsers && Boolean(userId),
        retry: false,
        refetchOnWindowFocus: false,
    });

    useEffect(() => {
        const loadedUser = userQuery.data?.data || null;

        if (!loadedUser) {
            return;
        }

        setUser(loadedUser);
        setForm({
            name: loadedUser.name || '',
            email: loadedUser.email || '',
            role: loadedUser.role || 'user',
            is_frozen: Boolean(loadedUser.is_frozen),
            password: '',
            password_confirmation: '',
        });
        setError('');
    }, [userQuery.data]);

    const updateUserMutation = useMutation({
        mutationFn: () => updateUser(userId, form),
        onSuccess: () => {
            window.location.href = '/dashboard/users?updated=1';
        },
        onError: (err) => {
            setError(err.message || 'Failed to update user.');
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
        updateUserMutation.mutate();
    };

    if (!canAccessUsers) {
        window.location.replace('/dashboard/unauthorized');
        return null;
    }

    const isLoading = userQuery.isLoading || userQuery.isFetching;
    const displayError = error || userQuery.error?.message || '';

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Edit User</h1>
                            <p className="mt-2 text-sm text-base-content/70">
                                Update this user record. Signed in as: <span className="font-semibold uppercase">{userRole}</span>
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/dashboard/users" className="btn btn-outline">Back</Link>
                            <Link href={`/dashboard/users/${userId}`} className="btn btn-ghost">View</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {isLoading ? <p className="text-sm text-base-content/70">Loading user...</p> : null}
                    {displayError ? <div className="alert alert-error py-2 text-sm">{displayError}</div> : null}

                    {!isLoading && !displayError && user ? (
                        <form className="grid gap-4 md:grid-cols-2" onSubmit={onSubmit}>
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
                            <span className="label-text mb-2">New Password</span>
                            <input
                                type="password"
                                name="password"
                                className="input input-bordered w-full"
                                value={form.password}
                                onChange={onFieldChange}
                                placeholder="Leave blank to keep current password"
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
                                placeholder="Only required when changing password"
                            />
                        </label>

                            <div className="md:col-span-2">
                                <button type="submit" className="btn btn-success" disabled={updateUserMutation.isPending}>
                                    {updateUserMutation.isPending ? 'Saving...' : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    ) : null}
                </div>
            </section>
        </div>
    );
}

export default EditUserPage;

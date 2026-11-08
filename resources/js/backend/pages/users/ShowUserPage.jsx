import React from 'react';
import { usePage } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { CompactDetailRow } from '@/backend/shared/CompactDetails';
import useAuth from '../../../shared/hooks/useAuth';
import { fetchUser } from './api/userApi';

function formatDateTime(value) {
    if (!value) {
        return 'Never';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Never';
    }

    return date.toLocaleString();
}

function ShowUserPage() {
    const { userId } = usePage().props;
    const { role: userRole } = useAuth();
    const canAccessUsers = ['admin', 'superadmin'].includes(userRole.toLowerCase());
    const userQuery = useQuery({
        queryKey: ['backend-user', userId],
        queryFn: () => fetchUser(userId),
        enabled: canAccessUsers && Boolean(userId),
        retry: false,
        refetchOnWindowFocus: false,
    });

    if (!canAccessUsers) {
        window.location.replace('/dashboard/unauthorized');
        return null;
    }

    const user = userQuery.data?.data || null;
    const isLoading = userQuery.isLoading || userQuery.isFetching;
    const error = userQuery.error?.message || '';

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">User Details</h1>
                            <p className="mt-2 text-sm text-base-content/70">
                                Review this user record. Signed in as: <span className="font-semibold uppercase">{userRole}</span>
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/dashboard/users" className="btn btn-outline">Back</Link>
                            <Link href={`/dashboard/users/${userId}/collections`} className="btn btn-secondary btn-outline">Collections</Link>
                            <Link href={`/dashboard/tapes?user_id=${userId}`} className="btn btn-success btn-outline">Catalog Entries</Link>
                            <Link href={`/dashboard/users/${userId}/edit`} className="btn btn-info btn-outline">Edit</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {isLoading ? <p className="text-sm text-base-content/70">Loading user...</p> : null}
                    {error ? <div className="alert alert-error py-2 text-sm">{error}</div> : null}

                    {!isLoading && !error && user ? (
                        <div className="grid gap-x-6 gap-y-2 md:grid-cols-2">
                            <CompactDetailRow title="User ID" value={user.id} />
                            <CompactDetailRow title="Role" value={<span className="uppercase">{user.role || 'user'}</span>} />
                            <CompactDetailRow title="Status" value={user.is_frozen ? 'Frozen' : 'Active'} />
                            <CompactDetailRow title="Name" value={user.name} />
                            <CompactDetailRow title="Email" value={user.email} />
                            <CompactDetailRow title="Collection Items" value={user.collection_items_count || 0} />
                            <CompactDetailRow title="Catalog Entries" value={user.catalog_entries_count || 0} />
                            <CompactDetailRow title="Created" value={formatDateTime(user.created_at)} />
                        </div>
                    ) : null}
                </div>
            </section>
        </div>
    );
}

export default ShowUserPage;

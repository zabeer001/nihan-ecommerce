import React, { useEffect, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { formatQuantity } from '@/shared/numberFormat';
import { Download, Edit3, Eye, RotateCcw, Search, Trash2, X } from 'lucide-react';
import { fetchCustomers } from '../customers/api/customerApi';
import { deleteDeliveryChallan, exportOrderChallans, fetchDeliveryChallans } from './api/deliveryChallanApi';

function DeliveryChallanPage() {
    const queryClient = useQueryClient();
    const didMountFilters = useRef(false);
    const initialParams = new URLSearchParams(window.location.search);
    const [message, setMessage] = useState(initialParams.get('created') === '1' ? 'Delivery challan created successfully.' : '');
    const [error, setError] = useState('');
    const [page, setPage] = useState(Number(initialParams.get('page') || 1));
    const [searchValue, setSearchValue] = useState(initialParams.get('search') || '');
    const [activeSearch, setActiveSearch] = useState(initialParams.get('search') || '');
    const [isExportModalOpen, setIsExportModalOpen] = useState(false);
    const [exportFilters, setExportFilters] = useState({
        customerId: '',
        challanDateFrom: '',
        challanDateTo: '',
    });
    const perPage = 15;

    useEffect(() => {
        if (initialParams.get('updated') === '1') {
            setMessage('Delivery challan updated successfully.');
        }
    }, []);

    const deliveryChallansQuery = useQuery({
        queryKey: ['delivery-challans', page, perPage, activeSearch],
        queryFn: () => fetchDeliveryChallans({ page, perPage, search: activeSearch }),
        retry: false,
        refetchOnWindowFocus: false,
    });
    const customersQuery = useQuery({
        queryKey: ['customers', 'delivery-challan-export'],
        queryFn: () => fetchCustomers({ perPage: 100 }),
        enabled: isExportModalOpen,
        retry: false,
        refetchOnWindowFocus: false,
    });

    function syncUrl(nextPage, nextSearch) {
        const params = new URLSearchParams();
        if (nextPage > 1) params.set('page', String(nextPage));
        if (nextSearch) params.set('search', nextSearch);
        const query = params.toString();
        window.history.replaceState({}, '', query ? `/dashboard/delivery-challan?${query}` : '/dashboard/delivery-challan');
    }

    useEffect(() => {
        if (!didMountFilters.current) {
            didMountFilters.current = true;
            return undefined;
        }

        const timeoutId = window.setTimeout(() => {
            const nextSearch = searchValue.trim();
            setPage(1);
            setActiveSearch(nextSearch);
            setMessage('');
            setError('');
            syncUrl(1, nextSearch);
        }, 300);

        return () => window.clearTimeout(timeoutId);
    }, [searchValue]);

    const deleteMutation = useMutation({
        mutationFn: (deliveryChallanId) => deleteDeliveryChallan(deliveryChallanId),
        onSuccess: (payload) => {
            setMessage(payload?.message || 'Delivery challan deleted successfully.');
            queryClient.invalidateQueries({ queryKey: ['delivery-challans'] });
        },
        onError: (err) => setError(err.message || 'Failed to delete delivery challan.'),
    });
    const exportMutation = useMutation({
        mutationFn: () => exportOrderChallans(exportFilters),
        onSuccess: () => setIsExportModalOpen(false),
    });

    const onExport = (event) => {
        event.preventDefault();
        setError('');
        setMessage('');
        exportMutation.mutate();
    };

    const closeExportModal = () => {
        if (!exportMutation.isPending) setIsExportModalOpen(false);
    };

    const onDelete = (deliveryChallan) => {
        if (!window.confirm(`Delete ${deliveryChallan.challan_number}?`)) return;
        setError('');
        setMessage('');
        deleteMutation.mutate(deliveryChallan.id);
    };

    const onReset = () => {
        setSearchValue('');
        setActiveSearch('');
        setPage(1);
        syncUrl(1, '');
    };

    const onPageChange = (nextPage) => {
        const parsedPage = Number(nextPage);
        if (!Number.isInteger(parsedPage) || parsedPage < 1) return;
        setPage(parsedPage);
        syncUrl(parsedPage, activeSearch);
    };

    const paginator = deliveryChallansQuery.data?.data || {};
    const deliveryChallans = Array.isArray(paginator.data) ? paginator.data : [];
    const currentPage = Number(paginator.current_page || page);
    const lastPage = Number(paginator.last_page || 1);
    const total = Number(paginator.total || 0);
    const isLoading = deliveryChallansQuery.isLoading || deliveryChallansQuery.isFetching;
    const displayError = error || deliveryChallansQuery.error?.message || '';
    const formatDeliveryQty = (deliveryChallan) => `${formatQuantity(deliveryChallan.delivery_qty)} ${deliveryChallan.delivery_unit || ''}`.trim();

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Delivery Challans</h1>
                            <p className="mt-2 text-sm text-base-content/70">Track shipped quantities, packages, and delivery status.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                className="btn btn-sm btn-outline"
                                disabled={exportMutation.isPending}
                                onClick={() => {
                                    setError('');
                                    exportMutation.reset();
                                    setIsExportModalOpen(true);
                                }}
                            >
                                <Download className="size-4" />
                                Export Order Challans
                            </button>
                            <Link href="/dashboard/delivery-challan/create" className="btn btn-sm btn-neutral text-neutral-content">
                                Add Delivery Challan
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {message ? <div className="alert alert-success py-2 text-sm">{message}</div> : null}
                    {displayError ? <div className="alert alert-error py-2 text-sm">{displayError}</div> : null}

                    <div className="mb-4 flex flex-col gap-2 rounded-xl border border-base-300 bg-base-100 p-2 shadow-sm sm:flex-row">
                        <label className="input input-bordered input-sm flex h-9 min-h-9 flex-1 items-center gap-1.5 rounded-lg border-base-300 bg-base-100 px-2">
                            <Search className="size-3.5 text-base-content/40" />
                            <input
                                type="search"
                                className="grow text-xs placeholder:text-base-content/40"
                                placeholder="Search by challan number, customer, order, package, or status"
                                value={searchValue}
                                onChange={(event) => setSearchValue(event.target.value)}
                            />
                        </label>
                        <button type="button" className="btn btn-sm h-9 min-h-9 rounded-lg" onClick={onReset}>
                            <RotateCcw className="size-3.5" />
                            Reset
                        </button>
                    </div>

                    {isLoading ? <p className="text-sm text-base-content/70">Loading delivery challans...</p> : null}

                    {!isLoading && !displayError ? (
                        <>
                            <div className="overflow-x-auto">
                                <table className="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Challan</th>
                                            <th>Customer</th>
                                            <th>Order</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th className="text-right">Qty</th>
                                            <th className="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {deliveryChallans.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="py-8 text-center text-base-content/70">No delivery challans found.</td>
                                            </tr>
                                        ) : (
                                            deliveryChallans.map((deliveryChallan) => (
                                                <tr key={deliveryChallan.id}>
                                                    <td className="font-medium">{deliveryChallan.challan_number}</td>
                                                    <td>{deliveryChallan.customer?.name || '-'}</td>
                                                    <td>{deliveryChallan.order?.order_number || '-'}</td>
                                                    <td>{deliveryChallan.challan_date || '-'}</td>
                                                    <td><span className="badge badge-outline">{deliveryChallan.status}</span></td>
                                                    <td className="text-right">{formatDeliveryQty(deliveryChallan)}</td>
                                                    <td>
                                                        <div className="flex flex-wrap justify-end gap-2">
                                                            <Link href={`/dashboard/delivery-challan/${deliveryChallan.id}`} className="btn btn-xs btn-outline">
                                                                <Eye className="size-3.5" />
                                                            </Link>
                                                            <Link href={`/dashboard/delivery-challan/${deliveryChallan.id}/edit`} className="btn btn-xs btn-info btn-outline">
                                                                <Edit3 className="size-3.5" />
                                                            </Link>
                                                            <button type="button" className="btn btn-xs btn-error btn-outline" onClick={() => onDelete(deliveryChallan)}>
                                                                <Trash2 className="size-3.5" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-base-content/70">Showing {paginator.from || 0}-{paginator.to || 0} of {total}</p>
                                <div className="join">
                                    <button type="button" className="btn join-item btn-sm" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>Previous</button>
                                    <button type="button" className="btn join-item btn-sm" disabled>Page {currentPage} of {lastPage}</button>
                                    <button type="button" className="btn join-item btn-sm" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>Next</button>
                                </div>
                            </div>
                        </>
                    ) : null}
                </div>
            </section>

            {isExportModalOpen ? (
                <div className="modal modal-open" role="dialog" aria-modal="true" aria-labelledby="export-modal-title">
                    <div className="modal-box max-w-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 id="export-modal-title" className="text-xl font-bold">Export Order Challans</h2>
                                <p className="mt-1 text-sm text-base-content/70">Choose the customer and challan date range, then export.</p>
                            </div>
                            <button
                                type="button"
                                className="btn btn-circle btn-ghost btn-sm"
                                aria-label="Close export modal"
                                disabled={exportMutation.isPending}
                                onClick={closeExportModal}
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        {exportMutation.error ? (
                            <div className="alert alert-error mt-4 py-2 text-sm">
                                {exportMutation.error.message || 'Failed to export delivery challans.'}
                            </div>
                        ) : null}

                        <form className="mt-5 space-y-4" onSubmit={onExport}>
                            <label className="form-control">
                                <span className="label-text mb-1 text-sm font-semibold">Customer</span>
                                <select
                                    className="select select-bordered w-full"
                                    value={exportFilters.customerId}
                                    disabled={customersQuery.isLoading || exportMutation.isPending}
                                    onChange={(event) => setExportFilters((current) => ({ ...current, customerId: event.target.value }))}
                                >
                                    <option value="">All customers</option>
                                    {(customersQuery.data?.data?.data || []).map((customer) => (
                                        <option key={customer.id} value={customer.id}>{customer.name}</option>
                                    ))}
                                </select>
                                {customersQuery.isError ? (
                                    <span className="mt-1 text-xs text-error">Could not load customers. You can still export all customers.</span>
                                ) : null}
                            </label>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className="form-control">
                                    <span className="label-text mb-1 text-sm font-semibold">Challan Date From</span>
                                    <input
                                        type="date"
                                        className="input input-bordered w-full"
                                        value={exportFilters.challanDateFrom}
                                        disabled={exportMutation.isPending}
                                        max={exportFilters.challanDateTo || undefined}
                                        onChange={(event) => setExportFilters((current) => ({ ...current, challanDateFrom: event.target.value }))}
                                    />
                                </label>
                                <label className="form-control">
                                    <span className="label-text mb-1 text-sm font-semibold">Challan Date To</span>
                                    <input
                                        type="date"
                                        className="input input-bordered w-full"
                                        min={exportFilters.challanDateFrom || undefined}
                                        value={exportFilters.challanDateTo}
                                        disabled={exportMutation.isPending}
                                        onChange={(event) => setExportFilters((current) => ({ ...current, challanDateTo: event.target.value }))}
                                    />
                                </label>
                            </div>

                            <div className="modal-action">
                                <button type="button" className="btn" disabled={exportMutation.isPending} onClick={closeExportModal}>Cancel</button>
                                <button type="submit" className="btn btn-neutral text-neutral-content" disabled={exportMutation.isPending}>
                                    <Download className="size-4" />
                                    {exportMutation.isPending ? 'Exporting...' : 'Export'}
                                </button>
                            </div>
                        </form>
                    </div>
                    <button type="button" className="modal-backdrop" aria-label="Close export modal" onClick={closeExportModal}>close</button>
                </div>
            ) : null}
        </div>
    );
}

export default DeliveryChallanPage;

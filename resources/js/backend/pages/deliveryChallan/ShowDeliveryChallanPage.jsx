import React from 'react';
import { usePage } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { formatAmount, formatQuantity } from '@/shared/numberFormat';
import { CompactDetailRow as DetailStat, CompactTextBlock } from '@/backend/shared/CompactDetails';
import { fetchDeliveryChallan } from './api/deliveryChallanApi';
import VariantAllocationEditor, { variantLinesFromChallan } from './VariantAllocationEditor';

function ShowDeliveryChallanPage() {
    const { deliveryChallanId } = usePage().props;
    const deliveryChallanQuery = useQuery({
        queryKey: ['delivery-challan', deliveryChallanId],
        queryFn: () => fetchDeliveryChallan(deliveryChallanId),
        enabled: Boolean(deliveryChallanId),
        retry: false,
        refetchOnWindowFocus: false,
    });

    const deliveryChallan = deliveryChallanQuery.data?.data || null;
    const isLoading = deliveryChallanQuery.isLoading || deliveryChallanQuery.isFetching;
    const error = deliveryChallanQuery.error?.message || '';
    const deliveryQty = deliveryChallan ? `${formatQuantity(deliveryChallan.delivery_qty)} ${deliveryChallan.delivery_unit || ''}`.trim() : '';
    const proformaInvoice = deliveryChallan?.proforma_invoice || null;

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Delivery Challan Details</h1>
                            <p className="mt-2 text-sm text-base-content/70">Review delivery quantity, package, and weight details.</p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/dashboard/delivery-challan" className="btn btn-outline">Back</Link>
                            <Link href={`/dashboard/delivery-challan/${deliveryChallanId}/edit`} className="btn btn-info btn-outline">Edit</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {isLoading ? <p className="text-sm text-base-content/70">Loading delivery challan...</p> : null}
                    {error ? <div className="alert alert-error py-2 text-sm">{error}</div> : null}
                    {!isLoading && !error && deliveryChallan ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            <DetailStat title="Challan Number" value={deliveryChallan.challan_number} />
                            <DetailStat title="Status" value={deliveryChallan.status} />
                            <DetailStat title="Customer" value={deliveryChallan.customer?.name} />
                            <DetailStat title="Order" value={deliveryChallan.order?.order_number} />
                            <DetailStat
                                title="Proforma Invoice"
                                value={proformaInvoice ? (
                                    <Link
                                        href={`/dashboard/proforma-invoices/${proformaInvoice.id}`}
                                        className="link link-primary font-semibold"
                                    >
                                        {proformaInvoice.pi_number || `PI #${proformaInvoice.id}`}
                                    </Link>
                                ) : '-'}
                            />
                            <DetailStat title="PI Date" value={proformaInvoice?.pi_date} />
                            <DetailStat title="Challan Date" value={deliveryChallan.challan_date} />
                            <DetailStat title="Delivery Qty" value={deliveryQty} />
                            <DetailStat title="Gross Weight" value={deliveryChallan.gross_weight ? formatAmount(deliveryChallan.gross_weight) : '-'} />
                            <DetailStat title="Net Weight" value={deliveryChallan.net_weight ? formatAmount(deliveryChallan.net_weight) : '-'} />
                            <CompactTextBlock title="Delivery Person" value={deliveryChallan.delivery_person_name} className="md:col-span-2" />
                            <CompactTextBlock title="Package Info" value={deliveryChallan.package_info} className="md:col-span-2" />
                            <CompactTextBlock title="Remarks" value={deliveryChallan.remarks} className="md:col-span-2" />
                            {deliveryChallan.variant_inventory?.variants?.length ? (
                                <VariantAllocationEditor
                                    inventory={deliveryChallan.variant_inventory}
                                    value={variantLinesFromChallan(deliveryChallan)}
                                    onChange={() => {}}
                                    currentChallanId={deliveryChallan.id}
                                    currentChallanDate={deliveryChallan.challan_date}
                                    readOnly
                                />
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </section>
        </div>
    );
}

export default ShowDeliveryChallanPage;

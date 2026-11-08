import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useMutation, useQuery } from '@tanstack/react-query';
import Link from '@/shared/CachedLink';
import { fetchDeliveryChallan, updateDeliveryChallan } from './api/deliveryChallanApi';
import { DeliveryChallanForm, EMPTY_FORM, generateChallanNumber, normalizePayload } from './CreateDeliveryChallanPage';
import { variantLinesFromChallan } from './VariantAllocationEditor';

function EditDeliveryChallanPage() {
    const { deliveryChallanId } = usePage().props;
    const [error, setError] = useState('');
    const [form, setForm] = useState(EMPTY_FORM);
    const [proformaSearch, setProformaSearch] = useState('');
    const [selectedProformaLabel, setSelectedProformaLabel] = useState('');
    const deliveryChallanQuery = useQuery({
        queryKey: ['delivery-challan', deliveryChallanId],
        queryFn: () => fetchDeliveryChallan(deliveryChallanId),
        enabled: Boolean(deliveryChallanId),
        retry: false,
        refetchOnWindowFocus: false,
    });

    useEffect(() => {
        const deliveryChallan = deliveryChallanQuery.data?.data;
        if (!deliveryChallan) return;

        setForm({
            customer_id: String(deliveryChallan.customer_id || ''),
            order_id: String(deliveryChallan.order_id || ''),
            proforma_invoice_id: String(deliveryChallan.proforma_invoice_id || ''),
            challan_number: deliveryChallan.challan_number || '',
            challan_date: deliveryChallan.challan_date || '',
            delivery_qty: String(deliveryChallan.delivery_qty ?? '0'),
            delivery_unit: deliveryChallan.delivery_unit || '',
            delivery_person_name: deliveryChallan.delivery_person_name || '',
            package_info: deliveryChallan.package_info || '',
            gross_weight: deliveryChallan.gross_weight ?? '',
            net_weight: deliveryChallan.net_weight ?? '',
            remarks: deliveryChallan.remarks || '',
            status: deliveryChallan.status || 'draft',
            variant_lines: variantLinesFromChallan(deliveryChallan),
        });

        if (deliveryChallan.proforma_invoice) {
            const label = `${deliveryChallan.proforma_invoice.pi_number || `PI #${deliveryChallan.proforma_invoice.id}`} · ${deliveryChallan.proforma_invoice.customer?.name || deliveryChallan.customer?.name || 'No customer'} · ${deliveryChallan.order?.order_number || deliveryChallan.order?.production_order_number || 'No order'}`;
            setProformaSearch(label);
            setSelectedProformaLabel(label);
        } else {
            setProformaSearch('');
            setSelectedProformaLabel('');
        }
    }, [deliveryChallanQuery.data]);

    const updateMutation = useMutation({
        mutationFn: () => updateDeliveryChallan(deliveryChallanId, normalizePayload(form)),
        onSuccess: () => {
            window.location.href = '/dashboard/delivery-challan?updated=1';
        },
        onError: (err) => setError(err.message || 'Failed to update delivery challan.'),
    });

    const onFieldChange = (event) => {
        const { name, value } = event.target;
        setForm((current) => ({
            ...current,
            [name]: value,
            ...(name === 'order_id' ? { variant_lines: [] } : {}),
        }));
    };

    const onProformaSearchChange = (event) => {
        setProformaSearch(event.target.value);
        setSelectedProformaLabel('');
        setForm((current) => ({ ...current, proforma_invoice_id: '' }));
    };

    const onSelectProformaInvoice = (proformaInvoice) => {
        const label = `${proformaInvoice.pi_number || `PI #${proformaInvoice.id}`} · ${proformaInvoice.customer?.name || 'No customer'} · ${proformaInvoice.order?.order_number || proformaInvoice.order?.production_order_number || 'No order'}`;

        setProformaSearch(label);
        setSelectedProformaLabel(label);
        setForm((current) => ({
            ...current,
            proforma_invoice_id: String(proformaInvoice.id || ''),
            customer_id: String(proformaInvoice.customer_id || proformaInvoice.customer?.id || current.customer_id || ''),
            order_id: String(proformaInvoice.order_id || proformaInvoice.order?.id || current.order_id || ''),
            variant_lines: [],
        }));
    };

    const onClearProformaInvoice = () => {
        setProformaSearch('');
        setSelectedProformaLabel('');
        setForm((current) => ({ ...current, proforma_invoice_id: '' }));
    };

    const onGenerateChallanNumber = () => {
        setForm((current) => ({ ...current, challan_number: generateChallanNumber() }));
    };

    const onSubmit = (event) => {
        event.preventDefault();
        setError('');

        if (!form.proforma_invoice_id) {
            setError('Please create and select a proforma invoice before saving this delivery challan.');
            return;
        }

        updateMutation.mutate();
    };

    const isLoading = deliveryChallanQuery.isLoading || deliveryChallanQuery.isFetching;
    const displayError = error || deliveryChallanQuery.error?.message || '';

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-3xl font-extrabold">Edit Delivery Challan</h1>
                            <p className="mt-2 text-sm text-base-content/70">Update delivery quantity, package, and status details.</p>
                        </div>
                        <div className="flex gap-2">
                            <Link href="/dashboard/delivery-challan" className="btn btn-outline">Back</Link>
                            <Link href={`/dashboard/delivery-challan/${deliveryChallanId}`} className="btn btn-ghost">View</Link>
                        </div>
                    </div>
                </div>
            </section>
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    {isLoading ? <p className="text-sm text-base-content/70">Loading delivery challan...</p> : null}
                    {displayError ? <div className="alert alert-error mb-4 py-2 text-sm">{displayError}</div> : null}
                    {!isLoading && !deliveryChallanQuery.error ? (
                        <DeliveryChallanForm
                            form={form}
                            onFieldChange={onFieldChange}
                            onSubmit={onSubmit}
                            isSaving={updateMutation.isPending}
                            submitLabel="Save Changes"
                            onGenerateChallanNumber={onGenerateChallanNumber}
                            proformaSearch={proformaSearch}
                            selectedProformaLabel={selectedProformaLabel}
                            onProformaSearchChange={onProformaSearchChange}
                            onSelectProformaInvoice={onSelectProformaInvoice}
                            onClearProformaInvoice={onClearProformaInvoice}
                            currentChallanId={deliveryChallanId}
                        />
                    ) : null}
                </div>
            </section>
        </div>
    );
}

export default EditDeliveryChallanPage;

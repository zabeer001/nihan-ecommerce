import React from 'react';
import { formatAmount } from '@/shared/numberFormat';

function formatQuantity(value) {
    return formatAmount(value);
}

function lineQuantity(lines, variantId) {
    return lines.find((line) => Number(line.order_item_style_id) === Number(variantId))?.quantity ?? '';
}

function isEarlierChallan(entry, currentChallanId, currentChallanDate) {
    if (!currentChallanId) {
        return true;
    }

    const entryDate = String(entry.challan_date || '');
    const currentDate = String(currentChallanDate || '');

    if (entryDate && currentDate && entryDate !== currentDate) {
        return entryDate < currentDate;
    }

    return Number(entry.challan_id) < Number(currentChallanId);
}

function previousDelivered(variant, currentChallanId, currentChallanDate) {
    return (variant.history || [])
        .filter((entry) => entry.status === 'delivered' && isEarlierChallan(entry, currentChallanId, currentChallanDate))
        .reduce((total, entry) => total + Number(entry.quantity || 0), 0);
}

function deliveredOnOtherChallans(variant, currentChallanId) {
    return (variant.history || [])
        .filter((entry) => entry.status === 'delivered' && Number(entry.challan_id) !== Number(currentChallanId))
        .reduce((total, entry) => total + Number(entry.quantity || 0), 0);
}

function variantLinesFromChallan(challan) {
    return Array.isArray(challan?.variant_lines)
        ? challan.variant_lines
            .filter((line) => line.order_item_style_id && !line.legacy_unallocated)
            .map((line) => ({
                order_item_style_id: Number(line.order_item_style_id),
                quantity: String(line.quantity ?? ''),
            }))
        : [];
}

function computedVariantTotal(lines) {
    return (lines || []).reduce((total, line) => total + Number(line.quantity || 0), 0);
}

function groupVariantTotal(lines, variants) {
    const variantIds = new Set(variants.map((variant) => Number(variant.order_item_style_id)));

    return computedVariantTotal(
        lines.filter((line) => variantIds.has(Number(line.order_item_style_id))),
    );
}

function VariantAllocationEditor({
    inventory,
    value,
    onChange,
    currentChallanId = null,
    currentChallanDate = null,
    readOnly = false,
}) {
    const variants = Array.isArray(inventory?.variants) ? inventory.variants : [];
    const legacy = Array.isArray(inventory?.legacy_unallocated) ? inventory.legacy_unallocated : [];
    const lines = Array.isArray(value) ? value : [];
    const groups = variants.reduce((result, variant) => {
        const key = String(variant.order_item_id);

        if (!result[key]) {
            result[key] = {
                itemName: variant.item_name || variant.item_code || 'Order item',
                variants: [],
            };
        }

        result[key].variants.push(variant);
        return result;
    }, {});

    const updateQuantity = (variantId, quantity) => {
        const existing = lines.find((line) => Number(line.order_item_style_id) === Number(variantId));
        const next = existing
            ? lines.map((line) => (
                Number(line.order_item_style_id) === Number(variantId)
                    ? { ...line, quantity }
                    : line
            ))
            : [...lines, { order_item_style_id: Number(variantId), quantity }];

        onChange(next);
    };

    if (!variants.length) {
        return null;
    }

    return (
        <div className="space-y-4 md:col-span-2">
            <div>
                <h3 className="font-semibold">Variant Delivery Allocation</h3>
                <p className="mt-1 text-sm text-base-content/60">Previously sent includes delivered challans only.</p>
            </div>

            {legacy.length ? (
                <div className="alert alert-warning py-3 text-sm">
                    This order has {formatQuantity(legacy.reduce((total, line) => total + Number(line.quantity || 0), 0))} in legacy unallocated challan quantities. Edit those challans to assign them to variants.
                </div>
            ) : null}

            {Object.entries(groups).map(([itemId, group]) => (
                <div key={itemId} className="overflow-hidden rounded-lg border border-base-300">
                    <div className="bg-base-200 px-4 py-3 font-semibold">{group.itemName}</div>
                    <div className="overflow-x-auto">
                        <table className="table table-sm">
                            <thead>
                                <tr>
                                    <th>Variant</th>
                                    <th className="text-right">Ordered</th>
                                    <th className="text-right">Previously Sent</th>
                                    <th className="text-right">Remaining</th>
                                    <th className="min-w-36 text-right">This Challan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {group.variants.map((variant) => {
                                    const previous = previousDelivered(variant, currentChallanId, currentChallanDate);
                                    const remaining = Math.max(Number(variant.ordered_qty || 0) - previous, 0);
                                    const availableForCurrent = Math.max(
                                        Number(variant.ordered_qty || 0) - deliveredOnOtherChallans(variant, currentChallanId),
                                        0,
                                    );
                                    const history = (variant.history || []).filter((entry) => (
                                        Number(entry.challan_id) !== Number(currentChallanId)
                                        && isEarlierChallan(entry, currentChallanId, currentChallanDate)
                                    ));

                                    return (
                                        <React.Fragment key={variant.order_item_style_id}>
                                            <tr>
                                                <td>
                                                    <p className="font-semibold">
                                                        {variant.variant_name || `Variant #${variant.variant_number}`}
                                                    </p>
                                                    <p className="text-xs text-base-content/60">
                                                        Variant #{variant.variant_number}
                                                        {variant.variant_code ? ` · ${variant.variant_code}` : ''}
                                                    </p>
                                                    {Object.keys(variant.custom_fields || {}).length ? (
                                                        <p className="mt-1 text-xs text-base-content/60">
                                                            {Object.entries(variant.custom_fields).map(([key, fieldValue]) => `${key}: ${fieldValue}`).join(' · ')}
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="text-right">{formatQuantity(variant.ordered_qty)} {variant.unit || ''}</td>
                                                <td className="text-right">{formatQuantity(previous)} {variant.unit || ''}</td>
                                                <td className="text-right font-semibold">{formatQuantity(remaining)} {variant.unit || ''}</td>
                                                <td>
                                                    {readOnly ? (
                                                        <p className="text-right font-semibold">{formatQuantity(lineQuantity(lines, variant.order_item_style_id))} {variant.unit || ''}</p>
                                                    ) : (
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max={availableForCurrent}
                                                            step="any"
                                                            className="input input-bordered input-sm w-full text-right"
                                                            value={lineQuantity(lines, variant.order_item_style_id)}
                                                            onChange={(event) => updateQuantity(variant.order_item_style_id, event.target.value)}
                                                            disabled={availableForCurrent <= 0}
                                                            placeholder={availableForCurrent <= 0 ? 'Completed' : ''}
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                            {history.length ? (
                                                <tr>
                                                    <td colSpan={5} className="pt-0">
                                                        <details className="text-xs">
                                                            <summary className="cursor-pointer text-primary">Previous challan history</summary>
                                                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                                                {history.map((entry) => (
                                                                    <div key={`${entry.challan_id}-${variant.order_item_style_id}`} className="rounded border border-base-300 bg-base-100 p-2">
                                                                        <span className="font-semibold">{entry.challan_number || `Challan #${entry.challan_id}`}</span>
                                                                        <span className="text-base-content/60"> · {entry.challan_date || '-'} · {entry.status}</span>
                                                                        <span className="float-right">{formatQuantity(entry.quantity)} {entry.unit || variant.unit || ''}</span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </details>
                                                    </td>
                                                </tr>
                                            ) : null}
                                        </React.Fragment>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colSpan={4}>Current Challan Total</th>
                                    <th className="text-right">{formatQuantity(groupVariantTotal(lines, group.variants))}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            ))}
        </div>
    );
}

export { computedVariantTotal, variantLinesFromChallan };
export default VariantAllocationEditor;

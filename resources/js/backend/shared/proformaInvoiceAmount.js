import { roundGlobalNumber } from '@/shared/numberFormat';

export function orderTotalAmount(order) {
    if (order?.total_amount !== null && order?.total_amount !== undefined && order?.total_amount !== '') {
        return roundGlobalNumber(order.total_amount);
    }

    if (Array.isArray(order?.items) && order.items.length) {
        return roundGlobalNumber(
            order.items.reduce((total, item) => total + Number(item.line_total || 0), 0),
        );
    }

    return roundGlobalNumber(order?.grand_total);
}

export function ordersTotalAmount(orders) {
    const uniqueOrders = new Map();

    (Array.isArray(orders) ? orders : []).forEach((order) => {
        if (!order) return;

        const key = order.id === null || order.id === undefined
            ? order
            : String(order.id);

        uniqueOrders.set(key, order);
    });

    return roundGlobalNumber(
        [...uniqueOrders.values()].reduce((total, order) => total + orderTotalAmount(order), 0),
    );
}

export function proformaInvoiceGrandTotal(orders, bankDocumentCharge = 0, freightCourierCharge = 0) {
    return roundGlobalNumber(
        ordersTotalAmount(orders)
        + Number(bankDocumentCharge || 0)
        + Number(freightCourierCharge || 0),
    );
}

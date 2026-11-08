import { formatAmount } from '@/shared/numberFormat';

export function calculateOrdersTotalWeight(orders = []) {
    return (Array.isArray(orders) ? orders : []).reduce((orderTotal, order) => (
        orderTotal + (Array.isArray(order?.items) ? order.items : []).reduce((itemTotal, item) => (
            itemTotal + (Number(item?.weight || 0) * Number(item?.quantity || 0))
        ), 0)
    ), 0);
}

export function formatWeightInKilograms(weight) {
    return `${formatAmount(Number(weight || 0) / 1000)} kg`;
}

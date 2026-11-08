export const GLOBAL_NUMBER_FORMAT = {
    locale: 'en-US',
    // Use 2 for 9.13, or null to preserve precision such as 9.1276312323.
    decimalPlaces: 2,
    fixedDecimalPlaces: true,
    rawMaximumDecimalPlaces: 10,
    useGrouping: true,
};

export const ITEM_VALUE_DECIMAL_PLACES = 5;
export const ITEM_CALCULATION_DECIMAL_PLACES = 10;

function numericValue(value) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

function decimalOptions({ fixed, decimalPlaces } = {}) {
    const places = decimalPlaces === undefined
        ? GLOBAL_NUMBER_FORMAT.decimalPlaces
        : decimalPlaces;
    const useFixedDecimalPlaces = fixed ?? GLOBAL_NUMBER_FORMAT.fixedDecimalPlaces;

    if (places === null) {
        return {
            minimumFractionDigits: 0,
            maximumFractionDigits: GLOBAL_NUMBER_FORMAT.rawMaximumDecimalPlaces,
        };
    }

    const normalizedPlaces = Math.max(0, Math.min(20, Number(places) || 0));

    return {
        minimumFractionDigits: useFixedDecimalPlaces ? normalizedPlaces : 0,
        maximumFractionDigits: normalizedPlaces,
    };
}

export function formatNumber(value, options = {}) {
    return numericValue(value).toLocaleString(
        options.locale || GLOBAL_NUMBER_FORMAT.locale,
        {
            ...decimalOptions(options),
            useGrouping: options.useGrouping ?? GLOBAL_NUMBER_FORMAT.useGrouping,
        },
    );
}

export function formatAmount(value, currency = '', options = {}) {
    const formatted = formatNumber(value, { ...options, fixed: true });

    return [currency, formatted].filter(Boolean).join(' ');
}

export function formatQuantity(value, options = {}) {
    return formatNumber(value, options);
}

export function formatInteger(value) {
    return formatNumber(value, { decimalPlaces: 0 });
}

export function roundGlobalNumber(value) {
    const numeric = numericValue(value);
    const decimalPlaces = GLOBAL_NUMBER_FORMAT.decimalPlaces;

    if (decimalPlaces === null) {
        return numeric;
    }

    const normalizedPlaces = Math.max(0, Math.min(20, Number(decimalPlaces) || 0));

    return Number(numeric.toFixed(normalizedPlaces));
}

export function formatItemValue(value, options = {}) {
    return formatNumber(value, {
        ...options,
        decimalPlaces: ITEM_VALUE_DECIMAL_PLACES,
    });
}

export function formatItemAmount(value, currency = '', options = {}) {
    const formatted = formatItemValue(value, { ...options, fixed: true });

    return [currency, formatted].filter(Boolean).join(' ');
}

export function calculateItemLineTotal(quantity, unitPrice) {
    const total = numericValue(quantity) * numericValue(unitPrice);

    return Number(total.toFixed(ITEM_CALCULATION_DECIMAL_PLACES));
}

export interface LineInput {
    quantity: number;
    unit_price: number | string;
    discount_type?: string | null;
    discount_value?: number | string | null;
    tax_rate?: number | string | null;
}

export interface DiscountInput {
    type?: string | null;
    value?: number | string | null;
}

export interface LineTotals {
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
}

export interface InvoiceTotals {
    subtotal: number;
    discount: number;
    tax: number;
    total: number;
    lines: LineTotals[];
}

function roundHalfUp(value: number): number {
    return value < 0 ? -Math.round(-value) : Math.round(value);
}

function toMinor(value: number | string, scale: number): number {
    return roundHalfUp(Number(value || 0) * Math.pow(10, scale));
}

/**
 * Client-side mirror of App\Services\Invoicing\InvoiceCalculator.
 *
 * This is only a live preview; the server recomputes authoritative totals on
 * save. Keep the two in sync if the calculation rules change.
 */
export function computeTotals(
    lines: LineInput[],
    discount: DiscountInput,
    taxInclusive: boolean,
    scale = 2,
): InvoiceTotals {
    const gross: number[] = [];
    const lineDiscounts: number[] = [];
    const nets: number[] = [];
    const rates: number[] = [];

    lines.forEach((line) => {
        const lineGross = roundHalfUp(Number(line.quantity || 0) * toMinor(line.unit_price, scale));
        const lineDiscount = discountAmount(lineGross, line.discount_type, line.discount_value, scale);
        const net = lineGross - lineDiscount;

        gross.push(lineGross);
        lineDiscounts.push(lineDiscount);
        nets.push(net);
        rates.push(Number(line.tax_rate || 0));
    });

    const subtotal = gross.reduce((a, b) => a + b, 0);
    const netTotal = nets.reduce((a, b) => a + b, 0);
    const invoiceDiscount = discountAmount(netTotal, discount.type, discount.value, scale);
    const shares = allocate(nets, invoiceDiscount);

    let tax = 0;
    let total = 0;
    const resultLines: LineTotals[] = lines.map((_, index) => {
        const base = nets[index] - shares[index];
        const rate = rates[index];
        const lineTax = taxInclusive
            ? roundHalfUp((base * rate) / (100 + rate))
            : roundHalfUp((base * rate) / 100);
        const lineTotal = taxInclusive ? base : base + lineTax;

        tax += lineTax;
        total += lineTotal;

        return {
            subtotal: gross[index],
            discount: lineDiscounts[index] + shares[index],
            tax: lineTax,
            total: lineTotal,
        };
    });

    return {
        subtotal,
        discount: lineDiscounts.reduce((a, b) => a + b, 0) + invoiceDiscount,
        tax,
        total,
        lines: resultLines,
    };
}

function discountAmount(base: number, type: string | null | undefined, value: number | string | null | undefined, scale: number): number {
    if (!type || value === null || value === undefined || Number(value) <= 0) {
        return 0;
    }

    const amount = type === 'percent' ? roundHalfUp((base * Number(value)) / 100) : toMinor(value, scale);

    return Math.max(0, Math.min(amount, base));
}

function allocate(weights: number[], amount: number): number[] {
    const total = weights.reduce((a, b) => a + b, 0);

    if (weights.length === 0 || amount === 0 || total <= 0) {
        return weights.map(() => 0);
    }

    let allocated = 0;
    const last = weights.length - 1;

    return weights.map((weight, index) => {
        if (index === last) {
            return amount - allocated;
        }
        const share = roundHalfUp((amount * weight) / total);
        allocated += share;

        return share;
    });
}

export function formatMinor(amountMinor: number, currency: string): string {
    const symbols: Record<string, string> = { USD: '$', EUR: '€', GBP: '£', JPY: '¥', INR: '₹', BDT: '৳' };
    const symbol = symbols[currency] ?? `${currency} `;
    const value = (amountMinor / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return `${symbol}${value}`;
}

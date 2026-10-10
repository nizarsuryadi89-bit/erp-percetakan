export type Product = {
    id: number;
    code: string;
    name: string;
    category: string;
    unit_name: string;
    pricing_basis: 'quantity' | 'length' | 'area';
    tier_basis: 'quantity' | 'billable';
    dimension_unit: 'cm' | 'm';
    minimum_length: string;
    minimum_width: string;
    is_active: boolean;
    prices_count?: number;
};
export type ProductData = Omit<Product, 'id' | 'prices_count'>;
export type ProductPrice = {
    id: number;
    detail_product: string;
    variant: string;
    machine: string;
    print_side: string;
    finishing: string;
    qty_min: string;
    qty_max: string | null;
    unit_price: string;
    hpp?: string;
    minimum_charge: string;
    is_active: boolean;
};
export type PriceQuote = {
    uuid: string;
    quoted_at: string;
    unit_name: string;
    product_name: string;
    input_length: string;
    input_width: string;
    billing_unit: string;
    tier_basis: 'quantity' | 'billable';
    lookup_qty: string;
    qty_min: string;
    qty_max: string | null;
    product_id: number;
    detail_product: string;
    qty: string;
    length: string;
    width: string;
    dimension_unit: string;
    pricing_basis: string;
    billable_qty: string;
    pricing_qty: string;
    unit_price: string;
    minimum_charge: string;
    minimum_applied: boolean;
    subtotal: string;
    specifications: {
        variant: string;
        machine: string;
        print_side: string;
        finishing: string;
    };
};
export const pricingLabels = {
    quantity: 'Jumlah',
    length: 'Panjang (meter)',
    area: 'Luas (m²)',
};
export const emptyProduct: ProductData = {
    code: '',
    name: '',
    category: '',
    unit_name: 'Lembar',
    pricing_basis: 'quantity',
    tier_basis: 'quantity',
    dimension_unit: 'cm',
    minimum_length: '0',
    minimum_width: '0',
    is_active: true,
};
export function rupiah(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

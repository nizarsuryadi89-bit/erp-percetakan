import type { PriceQuote, Product } from './product';
export type Calculation = Omit<PriceQuote, 'uuid' | 'quoted_at'>;
export type Customer = { id: number; name: string; phone: string | null; address: string | null };
export type OrderItem = { id: number; product_id: number; state: 'active' | 'removed' | 'superseded'; snapshot: Calculation; notes: string | null; process_location: string | null; deadline: string | null; replaces_item_id: number | null };
export type PrintOrder = {
    id: number; number: string; status: 'draft' | 'confirmed' | 'cancelled'; version: number; total: string;
    method: string; marketplace: { name: string; reference: string; tracking: string } | null;
    customer_snapshot: Omit<Customer, 'id'>;
    company_snapshot: { name: string; phone: string | null; address: string | null; branch_name: string; timezone: string };
    creator_name: string; notes: string | null; deadline: string | null; created_at: string;
    items: OrderItem[]; events: { id: number; action: string; actor_name: string; metadata: { from?: string; to?: string; reason?: string; item_id?: number; previous_item_id?: number }; created_at: string }[];
};
export type CatalogProduct = Product & { prices: { variant: string; machine: string; print_side: string; finishing: string }[] };
export const methodLabels: Record<string, string> = { walk_in: 'On the Spot / Walk-in', online: 'WhatsApp / Online', marketplace: 'Marketplace' };
export const statusLabels = { draft: 'Draft', confirmed: 'Terkonfirmasi', cancelled: 'Dibatalkan' };
export function dateLabel(value: string | null, timezone: string): string {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(value)) : 'Belum ditentukan';
}
export function dateInput(value: string | null, timezone: string): string {
    return value ? new Intl.DateTimeFormat('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: timezone }).format(new Date(value)).replace(' ', 'T') : '';
}

import type { Calculation, Customer, PrintOrder } from './deskprint';
export type PaymentMethod = { id: number; name: string; kind: 'cash' | 'transfer' | 'debit' | 'ewallet'; details: string | null; is_active?: boolean };
export type InvoicePayment = { id: number; entry_type: 'payment' | 'reversal'; reverses_payment_id: number | null; amount: string; method_snapshot: Omit<PaymentMethod, 'id' | 'is_active'>; reference: string | null; notes: string | null; reason: string | null; paid_at: string; created_at: string; actor_name: string };
export type InvoiceLine = { source_item_id: number; snapshot: Calculation; notes: string | null; process_location: string | null; deadline: string | null };
export type InvoiceSummary = { paid: string; balance: string; collectible: string; payment_status: 'unpaid' | 'partially_paid' | 'paid' };
export type Invoice = { id: number; print_order_id: number; number: string; order_number: string; branch_id: number; status: 'issued' | 'void'; total: string; issuer_name: string; issued_at: string; created_at: string; notes: string | null; company_snapshot: PrintOrder['company_snapshot']; customer_snapshot: Omit<Customer, 'id'>; items_snapshot: InvoiceLine[]; payments: InvoicePayment[]; summary: InvoiceSummary; events: { id: number; action: string; actor_name: string; metadata: { reason?: string }; created_at: string }[] };
export const paymentLabels = { unpaid: 'Belum bayar', partially_paid: 'Sebagian dibayar', paid: 'Lunas' };
export const methodKinds = { cash: 'Tunai', transfer: 'Transfer bank', debit: 'Debit / EDC', ewallet: 'E-wallet' };

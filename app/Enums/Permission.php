<?php

namespace App\Enums;

/**
 * Everything a store user may be allowed to do. The values are the `permissions.name` rows, so a new case needs a
 * migration that inserts its row before a role can be given it (a test fails while the two differ).
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';
    case CashierUse = 'cashier.use';
    case ProductsManage = 'products.manage';
    case StockOpnamesCount = 'stock_opnames.count';
    case StockOpnamesPost = 'stock_opnames.post';
    case StockHistoryView = 'stock_history.view';
    case SalesView = 'sales.view';
    case SalesVoid = 'sales.void';
    case DeliveryNotesView = 'delivery_notes.view';
    case DeliveryNotesManage = 'delivery_notes.manage';
    case ReturnsManage = 'returns.manage';
    case CodReceive = 'cod.receive';
    case CodApprove = 'cod.approve';
    case ShiftsReview = 'shifts.review';
    case ExpensesManage = 'expenses.manage';
    case DailyClosingsManage = 'daily_closings.manage';
    case ReportsView = 'reports.view';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Lihat dashboard toko',
            self::CashierUse => 'Pakai layar kasir (jual, shift, struk, retur & pengeluaran kasir)',
            self::ProductsManage => 'Kelola barang (daftar, ubah, harga massal, label)',
            self::StockOpnamesCount => 'Stok opname: buat dan hitung',
            self::StockOpnamesPost => 'Stok opname: review selisih dan posting',
            self::StockHistoryView => 'Lihat histori stok',
            self::SalesView => 'Lihat riwayat nota semua kasir',
            self::SalesVoid => 'Void nota',
            self::DeliveryNotesView => 'Lihat dan cetak surat jalan',
            self::DeliveryNotesManage => 'Buat, kirim, selesaikan, dan batalkan surat jalan',
            self::ReturnsManage => 'Kelola retur (buat, setujui, tolak)',
            self::CodReceive => 'Terima uang COD (konfirmasi pelunasan)',
            self::CodApprove => 'Setujui selisih penerimaan uang COD',
            self::ShiftsReview => 'Rekap shift: putuskan selisih kas',
            self::ExpensesManage => 'Kelola pengeluaran',
            self::DailyClosingsManage => 'Tutup hari dan buka kembali',
            self::ReportsView => 'Lihat laporan (penjualan dan stok)',
            self::SettingsManage => 'Ubah pengaturan toko dan satuan',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::DashboardView => 'Umum',
            self::ProductsManage, self::StockOpnamesCount, self::StockOpnamesPost, self::StockHistoryView => 'Barang & Stok',
            self::CashierUse, self::SalesView, self::SalesVoid, self::DeliveryNotesView, self::DeliveryNotesManage, self::ReturnsManage, self::CodReceive, self::CodApprove => 'Penjualan',
            self::ShiftsReview, self::ExpensesManage, self::DailyClosingsManage => 'Kas & Harian',
            self::ReportsView, self::SettingsManage => 'Laporan & Pengaturan',
        };
    }

    /**
     * The store page that this permission opens, where someone who may not see the dashboard lands (first match wins).
     */
    public function landingRouteName(): string
    {
        return match ($this) {
            self::DashboardView => 'tenant.dashboard',
            self::CashierUse, self::CodReceive, self::CodApprove => 'tenant.cashier.index',
            self::ProductsManage => 'tenant.products.index',
            self::StockOpnamesCount, self::StockOpnamesPost => 'tenant.stock-opnames.index',
            self::StockHistoryView => 'tenant.stock-history.index',
            self::SalesView, self::SalesVoid => 'tenant.sales.index',
            self::DeliveryNotesView, self::DeliveryNotesManage => 'tenant.delivery-notes.index',
            self::ReturnsManage => 'tenant.returns.index',
            self::ShiftsReview => 'tenant.shifts.index',
            self::ExpensesManage => 'tenant.expenses.index',
            self::DailyClosingsManage => 'tenant.daily-closings.index',
            self::ReportsView => 'tenant.reports.sales',
            self::SettingsManage => 'tenant.settings.index',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}

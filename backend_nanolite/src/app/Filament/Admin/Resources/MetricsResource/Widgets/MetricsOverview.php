<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

// models
use App\Models\Order;
use App\Models\Garansi;
use App\Models\ProductReturn;
use App\Models\Customer;
use App\Models\Perbaikandata;

// resources
use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\GaransiResource;
use App\Filament\Admin\Resources\ProductReturnResource;
use App\Filament\Admin\Resources\CustomerResource;
use App\Filament\Admin\Resources\PerbaikandataResource;

class MetricsOverview extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getCards(): array
    {
        // Pending
        $pendingOrders = Order::where('status_order', 'pending')->count();
        $pendingGaransi = Garansi::where('status_garansi', 'pending')->count();
        $pendingReturn  = ProductReturn::where('status_return', 'pending')->count();
        $pendingCustomer = Customer::where('status', 'pending')->count();
        $pendingPerbaikandata = Perbaikandata::where('status_pengajuan', 'pending')->count();

        // Delivered
        $deliveredOrders = Order::where('status_order', 'delivered')->count();
        $deliveredGaransi = Garansi::where('status_garansi', 'delivered')->count();
        $deliveredReturn  = ProductReturn::where('status_return', 'delivered')->count();

        // On Hold
        $onHoldOrders = Order::where('status_order', 'on_hold')->count();
        $onHoldGaransi = Garansi::where('status_garansi', 'on_hold')->count();
        $onHoldReturn  = ProductReturn::where('status_return', 'on_hold')->count();
        

        return [
            // Pending Section
            Card::make('Order Pending', $pendingOrders)
                ->description('Pengajuan order menunggu konfirmasi')
                ->icon('heroicon-o-shopping-bag')
                ->color('warning')
                ->url(OrderResource::getUrl('index')),

            Card::make('Garansi Pending', $pendingGaransi)
                ->description('Pengajuan garansi belum diproses')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->url(GaransiResource::getUrl('index')),

            Card::make('Return Pending', $pendingReturn)
                ->description('Pengajuan return belum disetujui')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->url(ProductReturnResource::getUrl('index')),

            Card::make('Customer Pending', $pendingCustomer)
                ->description('Pengajuan customer menunggu disetujui')
                ->icon('heroicon-o-user')
                ->color('warning')
                ->url(CustomerResource::getUrl('index')),

            Card::make('Perbaikan Data Pending', $pendingPerbaikandata)
                ->description('Pengajuan perbaikan data menunggu disetujui')
                ->icon('heroicon-o-book-open')
                ->color('warning')
                ->url(PerbaikandataResource::getUrl('index')),

            // Delivered Section
            Card::make('Order Delivered', $deliveredOrders)
                ->description('Order telah berhasil dikirim')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->url(OrderResource::getUrl('index')),

            Card::make('Garansi Delivered', $deliveredGaransi)
                ->description('Garansi telah diproses dan dikirim')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->url(GaransiResource::getUrl('index')),

            Card::make('Return Delivered', $deliveredReturn)
                ->description('Pengembalian barang telah diterima')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->url(ProductReturnResource::getUrl('index')),

            // On Hold Section
            Card::make('Order On Hold', $onHoldOrders)
                ->description('Order ditahan sementara menunggu keputusan')
                ->icon('heroicon-o-shopping-bag')
                ->color('danger')
                ->url(OrderResource::getUrl('index')),

            Card::make('Garansi On Hold', $onHoldGaransi)
                ->description('Proses garansi ditahan sementara')
                ->icon('heroicon-o-shield-check')
                ->color('danger')
                ->url(GaransiResource::getUrl('index')),

            Card::make('Return On Hold', $onHoldReturn)
                ->description('Proses return ditahan sementara')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->url(ProductReturnResource::getUrl('index')),

           
        ];
    }
}

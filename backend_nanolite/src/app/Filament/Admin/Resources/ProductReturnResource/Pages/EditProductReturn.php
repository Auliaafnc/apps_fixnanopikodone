<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Pages;

use App\Filament\Admin\Resources\ProductReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProductReturn extends EditRecord
{
    protected static string $resource = ProductReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $isSalesLike = $user->hasAnyRole(['sales','head_sales','head_digital']);

        // === SALES-LIKE ===
        if ($isSalesLike) {
            // hanya boleh edit jika status_return sudah delivered
            if (($this->record->status_return ?? null) !== 'delivered') {
                Notification::make()
                    ->title('Tidak bisa menyimpan')
                    ->body('Hanya boleh mengubah bukti delivered setelah status return = "delivered".')
                    ->danger()
                    ->send();

                $this->halt(); // batalkan save
            }

            // Batasi field yang boleh diubah
            $allowed = ['delivery_images', 'delivered_by', 'delivered_at'];
            $data = array_intersect_key($data, array_flip($allowed));

            // Jika ada upload delivered, auto lengkapi field
            if (!empty($data['delivery_images'])) {
                $data['delivered_at'] = $data['delivered_at'] ?? now();
                $data['delivered_by'] = $data['delivered_by'] ?? $user->employee_id;

                // Naikkan status ke delivered jika record masih tahap awal
                if (in_array($this->record->status_return, ['pending','confirmed','processing','on_hold'], true)) {
                    $data['status_return'] = 'delivered';
                }
            }

            return $data;
        }

        // === ADMIN/MANAGEMENT ===
        // konsistensi saat pengajuan ditolak
        if (($data['status_pengajuan'] ?? null) === 'rejected') {
            $data['status_product'] = 'rejected';
            $data['status_return']  = 'rejected';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

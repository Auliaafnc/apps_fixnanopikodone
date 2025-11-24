<?php

namespace App\Filament\Admin\Resources\GaransiResource\Pages;

use App\Filament\Admin\Resources\GaransiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditGaransi extends EditRecord
{
    protected static string $resource = GaransiResource::class;

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

        // === SALES / HEAD SALES / HEAD DIGITAL ===
        if ($isSalesLike) {
            // Hanya boleh edit jika record sudah delivered
            if (($this->record->status_garansi ?? null) !== 'delivered') {
                Notification::make()
                    ->title('Tidak bisa menyimpan')
                    ->body('Hanya boleh mengubah bukti delivered setelah status garansi = "delivered".')
                    ->danger()
                    ->send();

                $this->halt(); // batalkan penyimpanan
            }

            // Batasi field yang bisa diubah
            $allowed = ['delivery_images', 'delivered_by', 'delivered_at'];
            $data = array_intersect_key($data, array_flip($allowed));

            // Jika ada upload bukti delivered, auto lengkapi field terkait
            if (!empty($data['delivery_images'])) {
                $data['delivered_at'] = $data['delivered_at'] ?? now();
                $data['delivered_by'] = $data['delivered_by'] ?? $user->employee_id;

                // Naikkan status ke delivered jika sebelumnya masih awal
                if (in_array($this->record->status_garansi, ['pending','confirmed','processing','on_hold'], true)) {
                    $data['status_garansi'] = 'delivered';
                }
            }

            return $data;
        }

        // === ADMIN/MANAGEMENT ===
        // Konsistensi: jika pengajuan ditolak, status lain ikut ditolak
        if (($data['status_pengajuan'] ?? null) === 'rejected') {
            $data['status_product'] = 'rejected';
            $data['status_garansi'] = 'rejected';
        }

        return $data;
    }
}

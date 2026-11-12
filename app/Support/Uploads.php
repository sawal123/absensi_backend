<?php

namespace App\Support;

use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class Uploads
{
    /**
     * Simpan berkas ke disk publik dan kembalikan URL yang bisa dibuka.
     */
    public static function store(UploadedFile $file, string $folder = 'misc'): string
    {
        $folder = trim($folder, '/');
        $path = $file->store(self::prefix().'uploads/'.$folder, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Simpan berkas sensitif (selfie absensi) di disk privat.
     * Hanya path relatif yang dikembalikan, bukan URL publik.
     */
    public static function storePrivate(UploadedFile $file, string $folder = 'misc'): string
    {
        $folder = trim($folder, '/');

        return $file->store(self::prefix().$folder, 'local');
    }

    private static function prefix(): string
    {
        $tenantId = TenantContext::id();

        return $tenantId !== null ? 'tenant-'.$tenantId.'/' : 'platform/';
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Membuat 3 akun contoh (owner, admin, pelanggan) untuk pengujian.
 * Kata sandi dibaca dari .env (SEED_PASSWORD). Jika kosong, dibuat acak dan
 * ditampilkan SEKALI di terminal. Jangan menaruh kata sandi di kode atau commit.
 * Ganti email/nomor contoh dengan data asli sebelum dipakai sungguhan.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD') ?: Str::random(12);

        $accounts = [
            ['name' => 'Owner GeCe',     'email' => 'owner@gece.test',    'phone' => '081200000001', 'role' => 'owner'],
            ['name' => 'Admin Workshop', 'email' => 'admin@gece.test',    'phone' => '081200000002', 'role' => 'admin'],
            ['name' => 'Pelanggan Uji',  'email' => 'customer@gece.test', 'phone' => '081200000003', 'role' => 'customer'],
        ];

        foreach ($accounts as $a) {
            User::updateOrCreate(
                ['email' => $a['email']],
                $a + ['password' => $password, 'is_active' => true]
            );
        }

        $this->command->info('Akun dibuat: owner@gece.test, admin@gece.test, customer@gece.test');
        $this->command->warn('Kata sandi: '.$password.' (simpan sekarang, tidak ditampilkan lagi)');
    }
}

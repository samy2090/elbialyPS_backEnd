<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'site_name', 'value' => 'El Bialy PS', 'type' => 'string', 'group' => 'general'],
            ['key' => 'site_logo', 'value' => null, 'type' => 'string', 'group' => 'general'],
            ['key' => 'place_status', 'value' => 'open', 'type' => 'string', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general'],
            ['key' => 'discount_percent', 'value' => null, 'type' => 'decimal', 'group' => 'discount'],
            ['key' => 'discount_start_at', 'value' => null, 'type' => 'string', 'group' => 'discount'],
            ['key' => 'discount_end_at', 'value' => null, 'type' => 'string', 'group' => 'discount'],
            ['key' => 'posts_require_approval', 'value' => '0', 'type' => 'boolean', 'group' => 'posts'],
        ];

        foreach ($defaults as $row) {
            SiteSetting::updateOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'type' => $row['type'], 'group' => $row['group']]
            );
        }
    }
}

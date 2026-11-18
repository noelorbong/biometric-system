<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            'biometric_dtr_template2_logo_position' => 'left',
            'biometric_dtr_template2_logo_width' => '15',
            'biometric_dtr_template2_logo_height' => '15',
            'biometric_dtr_template2_logo_left' => '0',
            'biometric_dtr_template2_logo_right' => '4',
            'biometric_dtr_template2_logo_top' => '0',
            'biometric_dtr_template2_logo_bottom' => '3',
            'biometric_dtr_template2_company_font_size' => '8',
            'biometric_dtr_template2_republic_font_size' => '8',
            'biometric_dtr_template2_department_font_size' => '8',
            'biometric_dtr_template2_address_font_size' => '8',
            'biometric_dtr_template2_title_font_size' => '13',
            'biometric_dtr_template2_table_header_font_size' => '7',
            'biometric_dtr_template2_table_content_font_size' => '7',
            'biometric_dtr_template2_name_font_size' => '10',
            'biometric_dtr_template2_month_font_size' => '10',
            'biometric_dtr_template2_approved_font_size' => '10',
            'biometric_dtr_template2_timekeeper_font_size' => '10',
            'biometric_dtr_template2_employee_font_size' => '10',
        ];

        DB::transaction(function () use ($defaults) {
            foreach ($defaults as $key => $value) {
                $existing = DB::table('app_settings')->where('setting_key', $key)->exists();
                if ($existing) {
                    DB::table('app_settings')->where('setting_key', $key)->update([
                        'setting_value' => $value,
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('app_settings')->insert([
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve saved layout choices when rolling back this data preset.
    }
};

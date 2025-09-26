<?php

namespace Database\Seeders\Tenant;

use App\Models\InvoiceSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceSettingsSeeder extends Seeder
{
    public function run()
    {
        $baseData = [
            'prefix'              => 'salepro',
            'number_of_digit'     => 4,
            'numbering_type'      => 'datewise',
            'start_number'        => 1000,
            'header_text'         => 'SalePro',
            'footer_text'         => 'Thank you for shopping with us',
            'footer_title'        => 'Thank you for shopping with us',
            'size'                => 'a4',
            'logo_height'         => 200,
            'logo_width'          => 200,
            'primary_color'       => '#ff0000',
            'text_color'          => '#000000',
            'invoice_date_format' => 'd.m.y h:m A',
            'is_default'          => 0,
            'status'              => 0,
            'show_column' => json_encode([
                "is_default"            => 0,
                "status"                => 0,
                "show_barcode"          => 1,
                "show_qr_code"          => 1,
                "show_customer_details" => 1,
                "show_shipping_details" => 1,
                "show_payment_info"     => 1,
                "show_discount"         => 1,
                "show_tax_info"         => 1,
                "show_description"      => 1,
                "show_in_words"         => 1,
                "active_primary_color"  => 0,
                "active_text_color"     => 0,
                "show_warehouse_info"   => 1,
                "show_bill_to_info"     => 1,
                "show_footer_text"      => 1,
                "show_biller_info"      => 1,
                "show_payment_note"     => 1,
                "show_paid_info"        => 1,
                "show_ref_number"       => 1,
                "show_customer_name"    => 1,
                "active_date_format"    => 0,
                "active_generat_settings" => 0,
                "active_logo_height_width" => 0,
                "hide_total_due" => 0,
            ]),
        ];

        $templates = [
            'A4 Size Normal Invoice',
            '58mm Thermal Invoice',
            '80mm Thermal Invoice',
        ];

        $size = [
            'a4',
            '58mm',
            '80mm',
        ];

        $value = [
            1,
            0,
            0,
        ];

        if (InvoiceSetting::count() == 0) {
            foreach ($templates as $key => $name) {
                DB::table('invoice_settings')->insert(array_merge($baseData, [
                    'template_name' => $name,
                    'size' => $size[$key],
                    'is_default' => $value[$key]
                ]));
            }
        }
    }
}

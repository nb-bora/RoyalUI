<?php
declare(strict_types=1);

class InvoicePdfService
{
    public static function isAvailable(): bool
    {
        return file_exists(__DIR__ . '/../vendor/autoload.php');
    }

    public static function render(string $html, string $filename = 'facture.pdf'): string
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(
                'Bibliothèque PDF non installée. Exécutez : cd api && composer install'
            );
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'default_font' => 'dejavusans',
        ]);
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}

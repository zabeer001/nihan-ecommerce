<?php

namespace App\Support;

use Closure;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxDownload
{
    public static function fromHtml(string $fileName, Closure $render): StreamedResponse
    {
        return response()->streamDownload(function () use ($render): void {
            ob_start();
            $render();
            $html = (string) ob_get_clean();
            $html = preg_replace('/^\xEF\xBB\xBF/', '', $html) ?? $html;

            $spreadsheet = (new Html())->loadFromString($html);
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

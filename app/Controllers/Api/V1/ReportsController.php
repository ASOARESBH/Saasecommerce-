<?php
namespace App\Controllers\Api\V1;

use App\Core\ApiAuth;
use App\Core\ApiRequest;
use App\Services\AnalyticsService;

class ReportsController extends ApiController
{
    public function export(string $type): never
    {
        ApiAuth::require(['reports:read'], true);
        $format = strtolower((string) (ApiRequest::input('format', 'csv')));
        $report = (new AnalyticsService())->report($type, ['from' => ApiRequest::input('from'), 'to' => ApiRequest::input('to') ?: date('Y-m-d 23:59:59')]);
        if ($format !== 'csv') \App\Core\ApiResponse::error('Formato não suportado. Use csv.', 422, 'UNSUPPORTED_FORMAT');
        $filename = 'relatorio-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower($type)) . '-' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        if (!empty($report['rows'])) { fputcsv($out, array_keys((array) $report['rows'][0]), ';'); foreach ($report['rows'] as $row) fputcsv($out, (array) $row, ';'); }
        fclose($out); exit;
    }
}

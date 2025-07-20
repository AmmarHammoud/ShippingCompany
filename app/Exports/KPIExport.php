<?php

namespace App\Exports;

use App\Services\KpiService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KpiExport implements FromArray, WithHeadings
{
    protected $filters;
    protected $kpiService;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
        $this->kpiService = new KpiService();
    }

    public function array(): array
    {
        $data = $this->kpiService->getDashboardData($this->filters);

        return [[
            $data['summary']['avg_delivery_time'],
            $data['summary']['total_shipments'],
            $data['summary']['delivered_shipments'],
            $data['summary']['cancelled_shipments'],
        ]];
    }

    public function headings(): array
    {
        return [
            'متوسط وقت التوصيل (دقائق)',
            'عدد الشحنات الكلي',
            'عدد الشحنات المسلمة',
            'عدد الشحنات الملغاة',
        ];
    }
}

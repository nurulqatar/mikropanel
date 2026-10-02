<?php

namespace App\Http\Controllers;

use App\Models\HotspotBatch;
use App\Models\HotspotBranding;
use App\Models\HotspotVoucher;
use App\Services\CompanyBrandingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HotspotVoucherDocumentController extends Controller
{
    /*
     * HOTSPOT_COMPACT_PRINT_PRICE_V1
     */
    public function __construct(
        private readonly CompanyBrandingService $companyBranding
    ) {
    }

    public function printVoucher(
        Request $request,
        int $voucher
    ): View {
        $this->admin($request);

        return view(
            'hotspot.vouchers',
            [
                'items' => [
                    $this->item(
                        $this->voucher(
                            $voucher
                        )
                    ),
                ],

                'autoPrint' =>
                    true,

                'title' =>
                    'Hotspot Voucher',

                'branding' =>
                    HotspotBranding::current(),
            ]
        );
    }

    public function downloadVoucher(
        Request $request,
        int $voucher
    ): Response {
        $this->admin($request);

        $item =
            $this->item(
                $this->voucher(
                    $voucher
                )
            );

        return Pdf::loadView(
            'hotspot.vouchers',
            [
                'items' => [
                    $item,
                ],

                'autoPrint' =>
                    false,

                'title' =>
                    'Hotspot Voucher',

                'branding' =>
                    HotspotBranding::current(),
            ]
        )
            ->setPaper('a4')
            ->download(
                'hotspot-'
                . $item['username']
                . '.pdf'
            );
    }

    public function printBatch(
        Request $request,
        HotspotBatch $batch
    ): View {
        $this->admin($request);

        return view(
            'hotspot.vouchers',
            [
                'items' =>
                    $this->batchItems(
                        $batch
                    ),

                'autoPrint' =>
                    true,

                'title' =>
                    'Hotspot Voucher Batch '
                    . $batch
                        ->batch_code,

                'branding' =>
                    HotspotBranding::current(),
            ]
        );
    }

    public function downloadBatch(
        Request $request,
        HotspotBatch $batch
    ): Response {
        $this->admin($request);

        return Pdf::loadView(
            'hotspot.vouchers',
            [
                'items' =>
                    $this->batchItems(
                        $batch
                    ),

                'autoPrint' =>
                    false,

                'title' =>
                    'Hotspot Voucher Batch '
                    . $batch
                        ->batch_code,

                'branding' =>
                    HotspotBranding::current(),
            ]
        )
            ->setPaper('a4')
            ->download(
                'hotspot-batch-'
                . $batch
                    ->batch_code
                . '.pdf'
            );
    }

    private function voucher(
        int $id
    ): HotspotVoucher {
        return HotspotVoucher::withTrashed()
            ->with([
                'server',
                'plan',
            ])
            ->findOrFail($id);
    }

    private function batchItems(
        HotspotBatch $batch
    ): array {
        return HotspotVoucher::withTrashed()
            ->where(
                'hotspot_batch_id',
                $batch->id
            )
            ->with([
                'server',
                'plan',
            ])
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    HotspotVoucher $voucher
                ): array =>
                    $this->item(
                        $voucher
                    )
            )
            ->all();
    }

    private function item(
        HotspotVoucher $voucher
    ): array {
        $voucher->loadMissing([
            'server',
            'plan',
        ]);

        $company =
            $this->companyBranding
                ->forResellerId(
                    $voucher
                        ->server
                        ?->reseller_id
                );

        $companyName =
            trim(
                (string) (
                    $company[
                        'company_name'
                    ] ?? ''
                )
            );

        if ($companyName === '') {
            $companyName =
                'WiFi Service';
        }

        $currency =
            trim(
                (string) (
                    $company[
                        'currency'
                    ] ?? 'QAR'
                )
            );

        if ($currency === '') {
            $currency = 'QAR';
        }

        return [
            'company_name' =>
                $companyName,

            'username' =>
                $voucher->username,

            'price' =>
                $voucher->plan
                    ? (float)
                        $voucher
                            ->plan
                            ->price
                    : 0,

            'currency' =>
                $currency,

            'validity' =>
                $voucher->plan
                    ? (
                        $voucher
                            ->plan
                            ->validity_value
                        . ' '
                        . $voucher
                            ->plan
                            ->validity_unit
                    )
                    : '-',

            'dns_name' =>
                $voucher
                    ->server
                    ?->dns_name
                ?: '-',
        ];
    }

    private function admin(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->hasAnyPermission([
                    'hotspot.view',
                    'hotspot.manage',
                    'hotspot.sell',
                    'hotspot.payments',
                    'hotspot.export',
                ]),
            403
        );
    }
}

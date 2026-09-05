<?php

namespace App\Http\Controllers;

use App\Models\HotspotBatch;
use App\Models\HotspotBranding;
use App\Models\HotspotVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HotspotVoucherDocumentController extends Controller
{
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
        $payload =
            "MikroPanel Hotspot\n"
            . 'Server: '
            . (
                $voucher
                    ->server
                    ?->name
                ?? '-'
            )
            . "\nUsername: "
            . $voucher->username
            . "\nPassword: "
            . $voucher->password
            . "\nPlan: "
            . (
                $voucher
                    ->plan
                    ?->name
                ?? '-'
            );

        $qr =
            QrCode::create(
                $payload
            )
                ->setSize(170)
                ->setMargin(3);

        $svg =
            (new SvgWriter())
                ->write($qr)
                ->getString();

        $svg = preg_replace(
            '/<\?xml.*?\?>/s',
            '',
            $svg
        );

        return [
            'username' =>
                $voucher
                    ->username,

            'password' =>
                $voucher
                    ->password,

            'server' =>
                $voucher
                    ->server
                    ?->name,

            'dns_name' =>
                $voucher
                    ->server
                    ?->dns_name,

            'plan' =>
                $voucher
                    ->plan
                    ?->name,

            'price' =>
                $voucher
                    ->plan
                    ?->price,

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

            'rate_limit' =>
                $voucher
                    ->plan
                    ?->rate_limit,

            'qr_svg' =>
                $svg,
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

<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\IpRange;
use App\Models\Package;
use App\Services\ClientCustomFieldService;
use App\Services\ClientMigrationSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientMigrationController extends Controller
{
    public function index(
        Request $request,
        ClientCustomFieldService $fields
    ): Response {
        $user =
            $this->authorizeReseller(
                $request,
                'clients.view'
            );

        return Inertia::render(
            'Reseller/ClientMigration',
            [
                'packages' =>
                    Package::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'validity_days',
                        ]),

                'ipRanges' =>
                    IpRange::query()
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'start_ip',
                            'end_ip',
                        ]),

                'customFields' =>
                    $fields
                        ->definitions(),

                'permissions' => [
                    'import' =>
                        $user->hasPermission(
                            'clients.create'
                        ),

                    'export' =>
                        $user->hasPermission(
                            'clients.view'
                        ),

                    'form_builder' =>
                        $user->isResellerOwner()
                        || $user->hasPermission(
                            'settings.manage'
                        ),
                ],

                'importResult' =>
                    session(
                        'client_import_result'
                    ),
            ]
        );
    }

    public function template(
        Request $request,
        ClientMigrationSpreadsheetService
            $service
    ): BinaryFileResponse {
        $this->authorizeReseller(
            $request,
            'clients.view'
        );

        $path =
            $service->template();

        return response()
            ->download(
                $path,
                'MikroPanel_Client_Import_Template.xlsx'
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function export(
        Request $request,
        ClientMigrationSpreadsheetService
            $service
    ): BinaryFileResponse {
        $this->authorizeReseller(
            $request,
            'clients.view'
        );

        $path =
            $service->export();

        return response()
            ->download(
                $path,
                'MikroPanel_Clients_'
                . now(
                    'Asia/Qatar'
                )->format(
                    'Y-m-d_His'
                )
                . '.xlsx'
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function import(
        Request $request,
        ClientMigrationSpreadsheetService
            $service
    ): RedirectResponse {
        $this->authorizeReseller(
            $request,
            'clients.create'
        );

        $validated =
            $request->validate([
                'file' => [
                    'required',
                    'file',
                    'max:20480',
                    'mimes:xlsx,xls,csv',
                ],

                'default_package_id' => [
                    'required',
                    'integer',
                ],

                'default_ip_range_id' => [
                    'required',
                    'integer',
                ],
            ]);

        $result =
            $service->import(
                $validated[
                    'file'
                ],
                (int)
                $validated[
                    'default_package_id'
                ],
                (int)
                $validated[
                    'default_ip_range_id'
                ]
            );

        return back()
            ->with(
                'client_import_result',
                $result
            )
            ->with(
                'success',
                $result['success']
                . ' client(s) imported. '
                . $result['failed']
                . ' failed. No opening invoice/payment was created.'
            );
    }

    private function authorizeReseller(
        Request $request,
        string $permission
    ) {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isResellerUser()
            && (
                $user->isResellerOwner()
                || $user->hasPermission(
                    $permission
                )
            ),
            403
        );

        return $user;
    }
}

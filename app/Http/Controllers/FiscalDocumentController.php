<?php

namespace App\Http\Controllers;

use App\Models\FiscalDocument;
use App\Services\FiscalDocuments\FiscalDocumentIndexService;
use Illuminate\Support\Facades\Storage;
use App\Services\Permissions\ProfilePermissionService;
use Illuminate\Http\Request;

class FiscalDocumentController extends Controller
{
    public function index(Request $request, FiscalDocumentIndexService $service)
    {
        $this->authorizeFiscalDocumentPermission('fiscal_documents.view');

        $data = $service->build($request->query());
        $data['fiscalDocumentPermissions'] = $this->fiscalDocumentPermissions();

        if (! $data['fiscalDocumentPermissions']['fiscal_documents.view_values']) {
            $data = $this->maskFiscalDocumentValues($data);
        }

        return view('fiscal-documents.index', $data);
    }

    public function file(FiscalDocument $fiscalDocument)
    {
        $this->authorizeFiscalDocumentPermission('fiscal_documents.open_origin');
        abort_unless($fiscalDocument->tenant_id === request()->user()->tenant_id, 404);
        abort_unless(Storage::disk('local')->exists($fiscalDocument->pdf_path), 404);
        return Storage::disk('local')->download($fiscalDocument->pdf_path, 'nota-fiscal-'.$fiscalDocument->number.'.'.pathinfo($fiscalDocument->pdf_path, PATHINFO_EXTENSION));
    }
    private function authorizeFiscalDocumentPermission(string $permissionKey): void
    {
        abort_unless($this->canFiscalDocumentPermission($permissionKey), 403);
    }

    private function canFiscalDocumentPermission(string $permissionKey): bool
    {
        return app(ProfilePermissionService::class)->allows(request()->user(), $permissionKey, [
            'module' => 'fleet',
        ]);
    }

    private function fiscalDocumentPermissions(): array
    {
        $keys = [
            'fiscal_documents.view',
            'fiscal_documents.view_details',
            'fiscal_documents.open_origin',
            'fiscal_documents.view_values',
            'fiscal_documents.import',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->canFiscalDocumentPermission($key)])
            ->all();
    }

    private function maskFiscalDocumentValues(array $data): array
    {
        $data['summary']['total_amount'] = null;

        $data['documents'] = collect($data['documents'] ?? [])
            ->map(function (array $document) {
                $document['amount'] = null;
                $document['amount_label'] = 'Restrito';
                $document['details'] = collect($document['details'] ?? [])
                    ->reject(function (array $field) {
                        $label = mb_strtolower($field['label'] ?? '');

                        return str_contains($label, 'custo') || str_contains($label, 'valor');
                    })
                    ->values()
                    ->all();

                return $document;
            });

        return $data;
    }
}

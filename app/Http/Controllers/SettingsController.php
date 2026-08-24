<?php

namespace App\Http\Controllers;

use App\Services\TenantFiscalSettingService;
use Illuminate\Http\Request;
use App\Services\ActiveContextService;

class SettingsController extends Controller
{
    public function index(Request $request, TenantFiscalSettingService $fiscalSettings)
    {
        abort_unless($request->user() && (userHasProfile('admin') || userHasProfile('manager')), 403);
        $allowedTabs = ['general', 'aggregated-vehicles', 'fiscal-documents', 'permissions', 'system'];
        $tab = $request->query('tab', 'general');
        abort_unless(in_array($tab, $allowedTabs, true), 404);
        $fiscalRequirements = $fiscalSettings->requirements();
        $location = app(ActiveContextService::class)->activeLocation($request->user());
        return view('settings.index', compact('tab', 'fiscalRequirements', 'location'));
    }

    public function updateAggregatedVehicles(Request $request)
    {
        abort_unless($request->user() && (userHasProfile('admin') || userHasProfile('manager')), 403);
        $location = app(ActiveContextService::class)->activeLocation($request->user());
        abort_unless($location, 422, 'Selecione uma unidade para continuar.');
        $data = $request->validate(['allow_aggregated_fuel' => ['nullable', 'boolean'], 'allow_aggregated_maintenance' => ['nullable', 'boolean']]);
        $location->update(['allow_aggregated_fuel' => (bool) ($data['allow_aggregated_fuel'] ?? false), 'allow_aggregated_maintenance' => (bool) ($data['allow_aggregated_maintenance'] ?? false)]);
        return redirect()->route('settings.index', ['tab' => 'aggregated-vehicles'])->with('success', 'Permissões de veículos agregados salvas.');
    }

    public function updateFiscalDocuments(Request $request, TenantFiscalSettingService $fiscalSettings)
    {
        abort_unless($request->user() && (userHasProfile('admin') || userHasProfile('manager')), 403);
        $validated = $request->validate([
            'stock_entry' => ['nullable', 'boolean'],
            'external_fuel_filling' => ['nullable', 'boolean'],
            'fuel_receipt' => ['nullable', 'boolean'],
            'maintenance_external_service' => ['nullable', 'boolean'],
        ]);
        $fiscalSettings->updateRequirements($validated);
        return redirect()->route('settings.index', ['tab' => 'fiscal-documents'])->with('success', 'Configurações fiscais salvas.');
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Models\DailyChecklist;
use App\Models\DailyChecklistItem;
use App\Models\UserDivisionAccess;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ChecklistTemplate;
use App\Services\AuditLogService;

class DailyChecklistController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | OPTIONS
    |--------------------------------------------------------------------------
    | Retorna os contextos possíveis de checklist do usuário logado.
    */

    public function options(Request $request)
    {
        $user = auth()->user();
    
        $today =
            now()->toDateString();
    
        $accesses =
            UserDivisionAccess::with([
                    'division',
                    'location',
                ])
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('module', 'fleet')
                ->where('active', true)
                ->get();
    
        $templatesQuery =
            ChecklistTemplate::query()
                ->orderBy('name');
    
        /*
        |--------------------------------------------------------------------------
        | FILTRO DE ATIVO FLEXÍVEL
        |--------------------------------------------------------------------------
        */
    
        if (
            Schema::hasColumn('checklist_templates', 'active')
        ) {
            $templatesQuery->where('active', true);
        }
    
        if (
            Schema::hasColumn('checklist_templates', 'is_active')
        ) {
            $templatesQuery->where('is_active', true);
        }
    
        if (
            Schema::hasColumn('checklist_templates', 'status')
        ) {
            $templatesQuery->whereIn('status', [
                'active',
                'ativo',
                'Ativo',
            ]);
        }
    
        $templates =
            $templatesQuery->get();
    
        $options =
            collect();
    
        foreach ($accesses as $access) {
    
            $compatibleTemplates =
                $templates->filter(function ($template) use ($access) {
    
                    $templateProfile =
                        $template->profile
                        ?? $template->role
                        ?? $template->target_profile
                        ?? $template->target_role
                        ?? $template->profile_key
                        ?? null;
    
                    /*
                    |--------------------------------------------------------------------------
                    | SE O TEMPLATE NÃO TIVER PERFIL, ELE SERVE PARA TODOS
                    |--------------------------------------------------------------------------
                    */
    
                    if (! $templateProfile) {
                        return true;
                    }
    
                    return $this->normalizeProfile($templateProfile)
                        ===
                        $this->normalizeProfile($access->profile);
                });
    
            foreach ($compatibleTemplates as $template) {
    
                $checklist =
                    DailyChecklist::where('tenant_id', $user->tenant_id)
                        ->where('division_id', $access->division_id)
                        ->where('user_id', $user->id)
                        ->where('module', $access->module)
                        ->where('profile', $access->profile)
                        ->where('checklist_template_id', $template->id)
                        ->whereDate('checklist_date', $today)
                        ->when(
                            $access->location_id,
                            function ($query) use ($access) {
                                $query->where('location_id', $access->location_id);
                            },
                            function ($query) {
                                $query->whereNull('location_id');
                            }
                        )
                        ->first();
    
                $options->push([
                    'tenant_id' =>
                        $user->tenant_id,
    
                    'division_id' =>
                        $access->division_id,
    
                    'division_name' =>
                        optional($access->division)->name,
    
                    'location_id' =>
                        $access->location_id,
    
                    'location_name' =>
                        optional($access->location)->name,
    
                    'module' =>
                        $access->module,
    
                    'profile' =>
                        $access->profile,
    
                    'profile_label' =>
                        $this->profileLabel($access->profile),
    
                    'template_id' =>
                        $template->id,
    
                    'template_name' =>
                        $template->name,
    
                    'label' =>
                        $this->optionLabelWithTemplate(
                            $access,
                            $template
                        ),
    
                    'checklist_id' =>
                        optional($checklist)->id,
    
                    'status' =>
                        optional($checklist)->status ?? 'pending',
    
                    'status_label' =>
                        $this->statusLabel(
                            optional($checklist)->status ?? 'pending'
                        ),
                ]);
            }
        }
    
        return response()->json([
            'options' =>
                $options->values(),
    
            'debug' => [
                'accesses_count' =>
                    $accesses->count(),
    
                'templates_count' =>
                    $templates->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW OR CREATE
    |--------------------------------------------------------------------------
    | Recebe um contexto e retorna o checklist do dia.
    | Se não existir, cria com itens padrão.
    */

    public function showOrCreate(Request $request)
    {
        $user = auth()->user();

        $data =
            $request->validate([
                'division_id' => [
                    'required',
                    'exists:divisions,id',
                ],
                'template_id' => [
                    'required',
                    'exists:checklist_templates,id',
                ],
                'location_id' => [
                    'nullable',
                    'exists:locations,id',
                ],

                'module' => [
                    'required',
                    'in:fleet',
                ],

                'profile' => [
                    'required',
                    'in:driver,mechanic,supervisor,manager',
                ],
            ]);
        $template =
            ChecklistTemplate::with('items')
                ->where('active', true)
                ->findOrFail($data['template_id']);
        $this->ensureUserCanUseContext(
            $user,
            $data['division_id'],
            $data['location_id'] ?? null,
            $data['module'],
            $data['profile']
        );

        $today =
            now()->toDateString();

        $checklist =
            DailyChecklist::with([
                    'items',
                    'division',
                    'location',
                    'vehicle',
                ])
                ->where('checklist_template_id', $data['template_id'])
                ->where('tenant_id', $user->tenant_id)
                ->where('division_id', $data['division_id'])
                ->where('user_id', $user->id)
                ->where('module', $data['module'])
                ->where('profile', $data['profile'])
                ->whereDate('checklist_date', $today)
                ->when(
                    ! empty($data['location_id']),
                    function ($query) use ($data) {
                        $query->where('location_id', $data['location_id']);
                    },
                    function ($query) {
                        $query->whereNull('location_id');
                    }
                )
                ->latest('id')
                ->first();
        if (! $checklist) {

            $checklist =
                DB::transaction(function () use ($user, $data, $today, $template) {
                    $checklist =
                        DailyChecklist::create([

                            'tenant_id' =>
                                $user->tenant_id,
                            'checklist_template_id' =>
                                $template->id,
                            'division_id' =>
                                $data['division_id'],

                            'location_id' =>
                                $data['location_id'] ?? null,

                            'user_id' =>
                                $user->id,

                            'vehicle_id' =>
                                null,

                            'module' =>
                                $data['module'],

                            'profile' =>
                                $data['profile'],

                            'checklist_date' =>
                                $today,

                            'status' =>
                                'draft',

                            'notes' =>
                                null,

                            'completed_at' =>
                                null,

                        ]);

                        foreach ($template->items as $item) {
                        
                            DailyChecklistItem::create([
                        
                                'daily_checklist_id' =>
                                    $checklist->id,
                        
                                'key' =>
                                    $item->key
                                    ?? 'item_' . $item->id,
                        
                                'label' =>
                                    $item->label
                                    ?? $item->name
                                    ?? 'Item do checklist',
                        
                                'checked' =>
                                    false,
                        
                                'notes' =>
                                    null,
                        
                            ]);
                        }
                    return $checklist->load([
                        'items',
                        'division',
                        'location',
                        'vehicle',
                    ]);
                });
        }

        return response()->json([
            'checklist' =>
                $this->formatChecklist($checklist),

            'vehicles' =>
                $this->availableVehicles(
                    $user,
                    $data['division_id'],
                    $data['location_id'] ?? null
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    | Salva o checklist como rascunho.
    */

    public function save(Request $request, DailyChecklist $dailyChecklist)
    {
        $user = auth()->user();

        $this->ensureChecklistBelongsToUser(
            $dailyChecklist,
            $user
        );

        $data =
            $request->validate([
                'vehicle_id' => [
                    'nullable',
                    'exists:vehicles,id',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                'items' => [
                    'required',
                    'array',
                ],

                'items.*.id' => [
                    'required',
                    'exists:daily_checklist_items,id',
                ],

                'items.*.checked' => [
                    'nullable',
                    'boolean',
                ],

                'items.*.notes' => [
                    'nullable',
                    'string',
                ],
            ]);

        DB::transaction(function () use ($dailyChecklist, $data) {

            $dailyChecklist->update([

                'vehicle_id' =>
                    $data['vehicle_id'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,

                'status' =>
                    'draft',

                'completed_at' =>
                    null,

            ]);

            foreach ($data['items'] as $itemData) {

                DailyChecklistItem::where('id', $itemData['id'])
                    ->where('daily_checklist_id', $dailyChecklist->id)
                    ->update([

                        'checked' =>
                            ! empty($itemData['checked']),

                        'notes' =>
                            $itemData['notes'] ?? null,

                    ]);
            }
        });

        return response()->json([
            'message' =>
                'Checklist salvo como rascunho.',

            'checklist' =>
                $this->formatChecklist(
                    $dailyChecklist->fresh([
                        'items',
                        'division',
                        'location',
                        'vehicle',
                    ])
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPLETE
    |--------------------------------------------------------------------------
    | Conclui o checklist.
    */

    public function complete(Request $request, DailyChecklist $dailyChecklist)
    {
        $user = auth()->user();

        $this->ensureChecklistBelongsToUser(
            $dailyChecklist,
            $user
        );

        $data =
            $request->validate([
                'vehicle_id' => [
                    'nullable',
                    'exists:vehicles,id',
                ],

                'notes' => [
                    'nullable',
                    'string',
                ],

                'items' => [
                    'required',
                    'array',
                ],

                'items.*.id' => [
                    'required',
                    'exists:daily_checklist_items,id',
                ],

                'items.*.checked' => [
                    'nullable',
                    'boolean',
                ],

                'items.*.notes' => [
                    'nullable',
                    'string',
                ],
            ]);

        DB::transaction(function () use ($dailyChecklist, $data) {

            $dailyChecklist->update([

                'vehicle_id' =>
                    $data['vehicle_id'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,

                'status' =>
                    'completed',

                'completed_at' =>
                    now(),

            ]);

            foreach ($data['items'] as $itemData) {

                DailyChecklistItem::where('id', $itemData['id'])
                    ->where('daily_checklist_id', $dailyChecklist->id)
                    ->update([

                        'checked' =>
                            ! empty($itemData['checked']),

                        'notes' =>
                            $itemData['notes'] ?? null,

                    ]);
            }
        });

        $checklistAfter = $dailyChecklist->fresh('items');

        app(AuditLogService::class)->updated($checklistAfter, [
            'tenant_id' => $checklistAfter->tenant_id,
            'division_id' => $checklistAfter->division_id,
            'location_id' => $checklistAfter->location_id,
            'module' => 'checklists',
            'summary' => 'Checklist diario concluido.',
            'after_data' => $checklistAfter->toArray(),
            'metadata' => [
                'template_id' => $checklistAfter->checklist_template_id,
                'vehicle_id' => $checklistAfter->vehicle_id,
                'action_context' => 'daily_checklist_complete',
            ],
        ]);
        return response()->json([
            'message' =>
                'Checklist concluído.',

            'checklist' =>
                $this->formatChecklist(
                    $dailyChecklist->fresh([
                        'items',
                        'division',
                        'location',
                        'vehicle',
                    ])
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function ensureUserCanUseContext(
        $user,
        $divisionId,
        $locationId,
        $module,
        $profile
    ): void {
        $hasAccess =
            UserDivisionAccess::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->where('division_id', $divisionId)
                ->where('module', $module)
                ->where('profile', $profile)
                ->where('active', true)
                ->where(function ($query) use ($locationId) {
                    $query
                        ->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                })
                ->exists();

        if (! $hasAccess) {
            abort(403);
        }
    }

    private function ensureChecklistBelongsToUser(
        DailyChecklist $checklist,
        $user
    ): void {
        if (
            (int) $checklist->tenant_id !== (int) $user->tenant_id
            ||
            (int) $checklist->user_id !== (int) $user->id
        ) {
            abort(403);
        }
    }

    private function availableVehicles(
        $user,
        $divisionId,
        $locationId = null
    ) {
        return Vehicle::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('division_id', $divisionId)
            ->when(
                $locationId,
                function ($query) use ($locationId) {
                    $query->where('location_id', $locationId);
                }
            )
            ->orderBy('name')
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' =>
                        $vehicle->id,

                    'name' =>
                        $vehicle->name,

                    'plate' =>
                        $vehicle->plate,

                    'label' =>
                        trim(
                            ($vehicle->plate ? $vehicle->plate . ' · ' : '')
                            . $vehicle->name
                        ),
                ];
            })
            ->values();
    }

    private function formatChecklist(DailyChecklist $checklist): array
    {
        return [
            'id' =>
                $checklist->id,

            'tenant_id' =>
                $checklist->tenant_id,

            'division_id' =>
                $checklist->division_id,

            'division_name' =>
                optional($checklist->division)->name,

            'location_id' =>
                $checklist->location_id,

            'location_name' =>
                optional($checklist->location)->name,

            'user_id' =>
                $checklist->user_id,

            'vehicle_id' =>
                $checklist->vehicle_id,

            'vehicle_name' =>
                optional($checklist->vehicle)->name,
            'vehicle_label' =>
                $checklist->vehicle
                    ? trim(
                        ($checklist->vehicle->plate ? $checklist->vehicle->plate . ' · ' : '')
                        . $checklist->vehicle->name
                    )
                    : null,
            'module' =>
                $checklist->module,

            'profile' =>
                $checklist->profile,

            'profile_label' =>
                $this->profileLabel($checklist->profile),

            'checklist_date' =>
                optional($checklist->checklist_date)->format('Y-m-d'),

            'status' =>
                $checklist->status,

            'status_label' =>
                $this->statusLabel($checklist->status),

            'notes' =>
                $checklist->notes,
            'template_id' =>
                $checklist->checklist_template_id,
            
            'template_name' =>
                optional($checklist->template)->name,
                
            'completed_at' =>
                optional($checklist->completed_at)->format('d/m/Y H:i'),

            'items' =>
                $checklist->items
                    ->map(function ($item) {
                        return [
                            'id' =>
                                $item->id,

                            'key' =>
                                $item->key,

                            'label' =>
                                $item->label,

                            'checked' =>
                                (bool) $item->checked,

                            'notes' =>
                                $item->notes,
                        ];
                    })
                    ->values(),
        ];
    }

    private function defaultChecklistItems(): array
    {
        return [
            [
                'key' => 'oil_level',
                'label' => 'Nível de óleo conferido',
            ],
            [
                'key' => 'water_radiator',
                'label' => 'Água/radiador conferido',
            ],
            [
                'key' => 'tires',
                'label' => 'Pneus avaliados',
            ],
            [
                'key' => 'lights',
                'label' => 'Luzes funcionando',
            ],
            [
                'key' => 'brakes',
                'label' => 'Freios sem anormalidade',
            ],
            [
                'key' => 'documents',
                'label' => 'Documentos/equipamentos obrigatórios',
            ],
            [
                'key' => 'damage',
                'label' => 'Sem avarias novas',
            ],
        ];
    }

    private function profileLabel(?string $profile): string
    {
        return match ($profile) {
            'driver' =>
                'Motorista',

            'mechanic' =>
                'Mecânico',

            'supervisor' =>
                'Supervisor',

            'manager' =>
                'Gestor',

            default =>
                $profile ?? 'Perfil',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' =>
                'Em preenchimento',

            'completed' =>
                'Concluído',

            'pending' =>
                'Pendente',

            default =>
                'Pendente',
        };
    }

    private function optionLabel($access): string
    {
        $division =
            optional($access->division)->name ?? 'Divisão';

        $location =
            optional($access->location)->name ?? 'Todas unidades';

        $profile =
            $this->profileLabel($access->profile);

        return "{$division} · {$location} · {$profile}";
    }


    private function normalizeProfile(?string $profile): ?string
    {
        $profile = trim(
            mb_strtolower(
                $profile ?? ''
            )
        );
    
        return match ($profile) {
            'driver',
            'motorista' =>
                'driver',
    
            'mechanic',
            'mecanico',
            'mecânico' =>
                'mechanic',
    
            'supervisor' =>
                'supervisor',
    
            'manager',
            'gestor',
            'gestor operacional',
            'gestor_operacional' =>
                'manager',
    
            default =>
                $profile ?: null,
        };
    }
    
    private function optionLabelWithTemplate($access, $template): string
    {
        $division =
            optional($access->division)->name ?? 'Divisão';
    
        $location =
            optional($access->location)->name ?? 'Todas unidades';
    
        $templateName =
            $template->name ?? 'Checklist';
    
        return "{$division} · {$location} · {$templateName}";
    }
    
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Procedure;
use App\Models\StockCategory;

class ProcedureController extends Controller
{
    public function index()
    {
        $procedures =
            Procedure::with('fields')
            ->where('tenant_id', 1)
            ->latest()
            ->get();

        return view(

            'procedures.index',

            compact('procedures')

        );
    }

    public function create()
    {
        $categories =
            StockCategory::where(
                'tenant_id',
                1
            )->get();

        return view(

            'procedures.create',

            compact('categories')

        );
    }

    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÃO
        |--------------------------------------------------------------------------
        */
    
        $validated = $request->validate([
    
            'name' => [
                'required',
                'string',
                'max:255',
            ],
    
            'validity_km' => [
                'nullable',
                'boolean',
            ],
    
            'validity_hours' => [
                'nullable',
                'boolean',
            ],
    
            'validity_period' => [
                'nullable',
                'boolean',
            ],
    
            'interval_km' => [
                'exclude_unless:validity_km,1',
                'required',
                'numeric',
                'min:1',
            ],
            
            'interval_hours' => [
                'exclude_unless:validity_hours,1',
                'required',
                'numeric',
                'min:1',
            ],
            
            'interval_days' => [
                'exclude_unless:validity_period,1',
                'required',
                'numeric',
                'min:1',
            ],
    
            'can_be_internal' => [
                'required',
                'boolean',
            ],
    
            'fields' => [
                'nullable',
                'array',
            ],
    
            'fields.*.label' => [
                'required_with:fields',
                'string',
                'max:255',
            ],
    
            'fields.*.field_type' => [
                'required_with:fields',
                'string',
                'in:text,number,stock_item',
            ],
    
            'fields.*.stock_category_id' => [
                'nullable',
                'exists:stock_categories,id',
            ],
    
            'fields.*.has_quantity' => [
                'nullable',
                'boolean',
            ],
    
            'fields.*.required' => [
                'nullable',
                'boolean',
            ],
    
        ], [
    
            'name.required' =>
                'Informe o nome do procedimento.',
    
            'interval_km.required' =>
                'Informe o intervalo em KM.',
    
            'interval_km.min' =>
                'O intervalo em KM deve ser maior que zero.',
    
            'interval_hours.required' =>
                'Informe o intervalo em horas.',
    
            'interval_hours.min' =>
                'O intervalo em horas deve ser maior que zero.',
    
            'interval_days.required' =>
                'Informe o intervalo em dias.',
    
            'interval_days.min' =>
                'O intervalo em dias deve ser maior que zero.',
    
            'fields.*.label.required_with' =>
                'Informe o nome do campo adicional.',
    
            'fields.*.field_type.required_with' =>
                'Informe o tipo do campo adicional.',
    
            'fields.*.field_type.in' =>
                'O tipo do campo adicional é inválido.',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | FLAGS
        |--------------------------------------------------------------------------
        */
    
        $validityKm =
            $request->boolean('validity_km');
    
        $validityHours =
            $request->boolean('validity_hours');
    
        $validityPeriod =
            $request->boolean('validity_period');
    
        /*
        |--------------------------------------------------------------------------
        | CRIA PROCEDIMENTO
        |--------------------------------------------------------------------------
        */
    
        $procedure = Procedure::create([
    
            'tenant_id' =>
                auth()->user()->tenant_id ?? 1,
    
            'name' =>
                $validated['name'],
    
            'validity_km' =>
                $validityKm,
    
            'validity_hours' =>
                $validityHours,
    
            'validity_period' =>
                $validityPeriod,
    
            'interval_km' =>
                $validityKm
                    ? $validated['interval_km']
                    : 0,
    
            'interval_hours' =>
                $validityHours
                    ? $validated['interval_hours']
                    : 0,
    
            'interval_days' =>
                $validityPeriod
                    ? $validated['interval_days']
                    : 0,
    
            'can_be_internal' =>
                $request->boolean('can_be_internal'),
    
            'color' =>
                $request->color ?? '#22c55e',
    
            'icon' =>
                $request->icon ?? 'tool',
    
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | CAMPOS DINÂMICOS
        |--------------------------------------------------------------------------
        */
    
        foreach (
            $validated['fields'] ?? []
            as $field
        ) {
    
            if (empty($field['label'])) {
                continue;
            }
    
            $slug =
                Str::slug(
                    $field['label'],
                    '_'
                );
    
            $quantitySlug =
                !empty($field['has_quantity'])
                    ? $slug . '_quantity'
                    : null;
    
            $procedure->fields()->create([
    
                'label' =>
                    $field['label'],
    
                'slug' =>
                    $slug,
    
                'field_type' =>
                    $field['field_type'],
    
                'required' =>
                    !empty($field['required']),
    
                'stock_category_id' =>
                    $field['field_type'] === 'stock_item'
                        ? ($field['stock_category_id'] ?? null)
                        : null,
    
                'has_quantity' =>
                    $field['field_type'] === 'stock_item'
                        ? !empty($field['has_quantity'])
                        : false,
    
                'quantity_slug' =>
                    $field['field_type'] === 'stock_item'
                        ? $quantitySlug
                        : null,
    
                'sort_order' =>
                    $field['sort_order'] ?? 0,
    
            ]);
    
        }
    
        return redirect()
            ->route('procedures.index')
            ->with(
                'success',
                'Procedimento criado com sucesso.'
            );
    }

    public function edit(Procedure $procedure)
    {
        $procedure->load([
    
            'fields'
    
        ]);
    
        // dd($procedure->toArray());
    
        $categories =
            StockCategory::where(
                'tenant_id',
                1
            )->get();
    
        return view(
    
            'procedures.edit',
    
            compact(
                'procedure',
                'categories'
            )
    
        );
    }

public function update(
    Request $request,
    Procedure $procedure
) {
    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÃO
    |--------------------------------------------------------------------------
    */

    $validated = $request->validate([

        'name' => [
            'required',
            'string',
            'max:255',
        ],

        'validity_km' => [
            'nullable',
            'boolean',
        ],

        'validity_hours' => [
            'nullable',
            'boolean',
        ],

        'validity_period' => [
            'nullable',
            'boolean',
        ],

        'interval_km' => [
            'exclude_unless:validity_km,1',
            'required',
            'numeric',
            'min:1',
        ],
        
        'interval_hours' => [
            'exclude_unless:validity_hours,1',
            'required',
            'numeric',
            'min:1',
        ],
        
        'interval_days' => [
            'exclude_unless:validity_period,1',
            'required',
            'numeric',
            'min:1',
        ],

        'can_be_internal' => [
            'required',
            'boolean',
        ],

        'fields' => [
            'nullable',
            'array',
        ],

        'fields.*.label' => [
            'required_with:fields',
            'string',
            'max:255',
        ],

        'fields.*.field_type' => [
            'required_with:fields',
            'string',
            'in:text,number,stock_item',
        ],

        'fields.*.stock_category_id' => [
            'nullable',
            'exists:stock_categories,id',
        ],

        'fields.*.has_quantity' => [
            'nullable',
            'boolean',
        ],

        'fields.*.required' => [
            'nullable',
            'boolean',
        ],

    ], [

        'name.required' =>
            'Informe o nome do procedimento.',

        'interval_km.required' =>
            'Informe o intervalo em KM.',

        'interval_km.min' =>
            'O intervalo em KM deve ser maior que zero.',

        'interval_hours.required' =>
            'Informe o intervalo em horas.',

        'interval_hours.min' =>
            'O intervalo em horas deve ser maior que zero.',

        'interval_days.required' =>
            'Informe o intervalo em dias.',

        'interval_days.min' =>
            'O intervalo em dias deve ser maior que zero.',

        'fields.*.label.required_with' =>
            'Informe o nome do campo adicional.',

        'fields.*.field_type.required_with' =>
            'Informe o tipo do campo adicional.',

        'fields.*.field_type.in' =>
            'O tipo do campo adicional é inválido.',

    ]);

    /*
    |--------------------------------------------------------------------------
    | FLAGS
    |--------------------------------------------------------------------------
    */

    $validityKm =
        $request->boolean('validity_km');

    $validityHours =
        $request->boolean('validity_hours');

    $validityPeriod =
        $request->boolean('validity_period');

    /*
    |--------------------------------------------------------------------------
    | ATUALIZA PROCEDIMENTO
    |--------------------------------------------------------------------------
    */

    $procedure->update([

        'name' =>
            $validated['name'],

        'validity_km' =>
            $validityKm,

        'validity_hours' =>
            $validityHours,

        'validity_period' =>
            $validityPeriod,

        'interval_km' =>
            $validityKm
                ? $validated['interval_km']
                : 0,

        'interval_hours' =>
            $validityHours
                ? $validated['interval_hours']
                : 0,

        'interval_days' =>
            $validityPeriod
                ? $validated['interval_days']
                : 0,

        'can_be_internal' =>
            $request->boolean('can_be_internal'),

    ]);

    /*
    |--------------------------------------------------------------------------
    | RECRIA CAMPOS
    |--------------------------------------------------------------------------
    */

    $procedure->fields()->delete();

    foreach (
        $validated['fields'] ?? []
        as $field
    ) {

        if (empty($field['label'])) {
            continue;
        }

        $slug =
            Str::slug(
                $field['label'],
                '_'
            );

        $quantitySlug =
            !empty($field['has_quantity'])
                ? $slug . '_quantity'
                : null;

        $procedure->fields()->create([

            'label' =>
                $field['label'],

            'slug' =>
                $slug,

            'field_type' =>
                $field['field_type'],

            'required' =>
                !empty($field['required']),

            'stock_category_id' =>
                $field['field_type'] === 'stock_item'
                    ? ($field['stock_category_id'] ?? null)
                    : null,

            'has_quantity' =>
                $field['field_type'] === 'stock_item'
                    ? !empty($field['has_quantity'])
                    : false,

            'quantity_slug' =>
                $field['field_type'] === 'stock_item'
                    ? $quantitySlug
                    : null,

            'sort_order' =>
                $field['sort_order'] ?? 0,

        ]);

    }

    return redirect()
        ->route('procedures.index')
        ->with(
            'success',
            'Procedimento atualizado com sucesso.'
        );
}
}
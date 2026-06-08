<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\Vehicle;
use App\Models\ChecklistTemplate;

use App\Models\ChecklistExecution;
use App\Models\ChecklistExecutionAnswer;

class ChecklistExecutionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    public function start(
        Vehicle $vehicle,
        ChecklistTemplate $template
    ) {

        $template->load([
            'items' => function ($query) {

                $query->where(
                    'is_active',
                    true
                );

            }
        ]);

        return view(

            'checklists.execute',

            compact(
                'vehicle',
                'template'
            )

        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Vehicle $vehicle,
        ChecklistTemplate $template
    ) {

        $execution =
            ChecklistExecution::create([

                'division_id' =>
                    session('active_division_id'),

                'vehicle_id' =>
                    $vehicle->id,

                'checklist_template_id' =>
                    $template->id,

                'user_id' =>
                    auth()->id(),

                'status' =>
                    'completed',

                'executed_at' =>
                    Carbon::now()

            ]);

        foreach ($template->items as $item)
        {
            $value =
                $request->input(
                    'item_'.$item->id
                );

            $photoPath = null;

            /*
            |--------------------------------------------------------------------------
            | FOTO
            |--------------------------------------------------------------------------
            */

            if(
                $item->field_type === 'photo'
                &&
                $request->hasFile(
                    'item_'.$item->id
                )
            ) {

                $photoPath =
                    $request
                        ->file(
                            'item_'.$item->id
                        )
                        ->store(
                            'checklists',
                            'public'
                        );
            }

            ChecklistExecutionAnswer::create([

                'checklist_execution_id' =>
                    $execution->id,

                'checklist_template_item_id' =>
                    $item->id,

                'answer' =>
                    $value,

                'photo_path' =>
                    $photoPath

            ]);

            /*
            |--------------------------------------------------------------------------
            | AUTO UPDATE KM/HR
            |--------------------------------------------------------------------------
            */

            if(
                str_contains(
                    strtolower($item->label),
                    'km'
                )
            ) {

                $vehicle->update([
                    'current_km' => $value
                ]);
            }

            if(
                str_contains(
                    strtolower($item->label),
                    'horimetro'
                )
                ||
                str_contains(
                    strtolower($item->label),
                    'hr'
                )
            ) {

                $vehicle->update([
                    'current_hours' => $value
                ]);
            }
        }

        return redirect()
            ->route(
                'vehicles.show',
                $vehicle
            )
            ->with(
                'success',
                'Checklist realizado.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        ChecklistExecution $execution
    ) {

        $execution->load([
            'vehicle',
            'user',
            'template',
            'answers.item'
        ]);

        return view(

            'checklists.show',

            compact('execution')

        );
    }
}
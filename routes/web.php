<?php



use Illuminate\Support\Facades\Route;



use App\Http\Controllers\AccessControlController;

use App\Http\Controllers\ActiveLocationController;

use App\Http\Controllers\AuditLogController;

use App\Http\Controllers\ChecklistController;

use App\Http\Controllers\ChecklistExecutionController;

use App\Http\Controllers\DailyChecklistController;

use App\Http\Controllers\DashboardController;

use App\Http\Controllers\FuelTankController;

use App\Http\Controllers\MaintenanceController;

use App\Http\Controllers\PortalController;

use App\Http\Controllers\ProcedureController;

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\ReportController;

use App\Http\Controllers\StockController;

use App\Http\Controllers\VehicleController;

use App\Http\Controllers\VehicleTireController;

use App\Http\Controllers\WorkshopTireController;

use App\Http\Controllers\LocationController;

use App\Http\Controllers\WorkshopController;

use App\Http\Controllers\VehicleOperationController;



/*

|--------------------------------------------------------------------------

| PUBLIC

|--------------------------------------------------------------------------

*/



Route::get('/', function () {

    return view('welcome');

});





/*

|--------------------------------------------------------------------------

| AUTHENTICATED ROUTES

|--------------------------------------------------------------------------

*/



Route::middleware('auth')->group(function () {

    Route::patch('/locations/{location}/toggle-active', [LocationController::class, 'toggleActive'])
        ->name('locations.toggle-active');

    Route::get(
        '/reports/vehicle-dossier/pdf',
        [ReportController::class, 'pdf']
    )->name('reports.vehicle-dossier.pdf');
    /*

    |--------------------------------------------------------------------------

    | PROFILE

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/profile',

        [ProfileController::class, 'edit']

    )->name('profile.edit');

    Route::get(

        '/profile/security',

        [ProfileController::class, 'security']

    )->name('profile.security');

    Route::get(

        '/profile/settings',

        [ProfileController::class, 'settings']

    )->name('profile.settings');



    Route::patch(

        '/profile',

        [ProfileController::class, 'update']

    )->name('profile.update');



    Route::delete(

        '/profile',

        [ProfileController::class, 'destroy']

    )->name('profile.destroy');


    Route::get(

        '/audit',

        [AuditLogController::class, 'index']

    )

        ->middleware('can:viewAuditLogs')

        ->name('audit.index');





    /*

    |--------------------------------------------------------------------------

    | PORTAL / DASHBOARD / DIVISIONS

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/',

        [PortalController::class, 'index']

    )->name('portal');



    Route::get(

        '/fleet/dashboard',

        [DashboardController::class, 'index']

    )->name('dashboard');



    Route::get(

        '/division/{division}',

        [PortalController::class, 'division']

    )->name('division.modules');



    Route::get(

        '/division/{division}/enter',

        [PortalController::class, 'enterDivision']

    )->name('division.enter');



    Route::get(

        '/leave-division',

        [PortalController::class, 'leaveDivision']

    )->name('division.leave');

    Route::post(
        '/active-location',
        [ActiveLocationController::class, 'update']
    )->name('active-location.update');





    /*

    |--------------------------------------------------------------------------

    | DAILY CHECKLISTS

    |--------------------------------------------------------------------------

    */



    Route::prefix('daily-checklists')

        ->name('daily-checklists.')

        ->controller(DailyChecklistController::class)

        ->group(function () {



            Route::get(

                '/options',

                'options'

            )->name('options');



            Route::post(

                '/show-or-create',

                'showOrCreate'

            )->name('show-or-create');



            Route::post(

                '/{dailyChecklist}/save',

                'save'

            )->name('save');



            Route::post(

                '/{dailyChecklist}/complete',

                'complete'

            )->name('complete');



        });





    /*

    |--------------------------------------------------------------------------

    | VEHICLES

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/vehicles',

        [VehicleController::class, 'index']

    )->name('vehicles.index');



    Route::get(

        '/vehicles/create',

        [VehicleController::class, 'create']

    )->name('vehicles.create');



    Route::post(

        '/vehicles',

        [VehicleController::class, 'store']

    )->name('vehicles.store');



    Route::get(

        '/vehicles/{vehicle}/edit',

        [VehicleController::class, 'edit']

    )->name('vehicles.edit');



    Route::put(

        '/vehicles/{vehicle}',

        [VehicleController::class, 'update']

    )->name('vehicles.update');



    Route::post(

        '/vehicles/{vehicle}/operational-status',

        [VehicleController::class, 'updateOperationalStatus']

    )->name('vehicles.operational-status.update');



    Route::post(

        '/vehicles/{vehicle}/update-km',

        [VehicleController::class, 'updateKm']

    )->name('vehicles.update-km');



    Route::post(

        '/vehicles/{vehicle}/update-hours',

        [VehicleController::class, 'updateHours']

    )->name('vehicles.update-hours');



    Route::get(

        '/vehicle/{vehicle}/history',

        [VehicleController::class, 'history']

    )->name('vehicles.history');

    

    Route::get(

        '/vehicles/{vehicle}/details',

        [VehicleController::class, 'details']

    )->name('vehicles.details');





    /*

    |--------------------------------------------------------------------------

    | VEHICLE MAINTENANCE

    |--------------------------------------------------------------------------

    */



    Route::post('/vehicles/{vehicle}/maintenance', [MaintenanceController::class, 'store'])
        ->name('vehicles.maintenance.store');

    // Route::post('/vehicles/{vehicle}/maintenance/{maintenance}/cancel', [MaintenanceController::class, 'cancel'])
    //     ->name('vehicles.maintenance.cancel');

    Route::get(

        '/vehicle/{vehicle}/maintenance',

        [VehicleController::class, 'maintenanceIndex']

    )->name('vehicle.maintenance.index');

    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/status',
        [MaintenanceController::class, 'changeStatus']
    )->name('vehicles.maintenance.status');

    Route::get(

        '/vehicle/{vehicle}/maintenance/create',

        [VehicleController::class, 'maintenanceCreate']

    )->name('vehicle.maintenance.create');

    Route::get(
        '/vehicles/{vehicle}/maintenance/{maintenance}/add-item',
        [MaintenanceController::class, 'addItemCreate']
    )->name('vehicles.maintenance.items.create');
    
    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/items',
        [MaintenanceController::class, 'storeItem']
    )->name('vehicles.maintenance.items.store');


    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/close',
        [MaintenanceController::class, 'close']
    )->name('vehicles.maintenance.close');
    
    Route::post(
        '/vehicle/{vehicle}/maintenance/{maintenance}/cancel',
        [MaintenanceController::class, 'cancel']
    )->name('vehicles.maintenance.cancel');
    
    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/extra-costs',
        [MaintenanceController::class, 'storeExtraCost']
    )->name('vehicles.maintenance.extra-costs.store');
    
    Route::get(
        '/vehicles/{vehicle}/maintenance/{maintenance}/pdf',
        [MaintenanceController::class, 'exportOrderPdf']
    )->name('vehicles.maintenance.order.pdf');
    
    Route::get(
        '/vehicles/{vehicle}/maintenance/{maintenance}',
        [MaintenanceController::class, 'show']
    )->name('vehicles.maintenance.show');
    
    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/reopen',
        [MaintenanceController::class, 'reopen']
    )->name('vehicles.maintenance.reopen');
    
    Route::post(
        '/vehicles/{vehicle}/maintenance/{maintenance}/delete',
        [MaintenanceController::class, 'destroy']
    )->name('vehicles.maintenance.destroy');
    /*

    |--------------------------------------------------------------------------

    | VEHICLE QUICK UPDATE

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/vehicle/quick-update',

        [VehicleController::class, 'quickUpdate']

    )->name('vehicle.quick-update');



    Route::post(

        '/vehicle/quick-update',

        [VehicleController::class, 'quickUpdateStore']

    )->name('vehicle.quick-update.store');





    /*

    |--------------------------------------------------------------------------

    | VEHICLE TIRES

    |--------------------------------------------------------------------------

    */



    Route::prefix('vehicles/{vehicle}/tires')

        ->name('vehicles.tires.')

        ->controller(VehicleTireController::class)

        ->group(function () {



            Route::get(

                '/',

                'index'

            )->name('index');



            Route::post(

                '/install',

                'storeInstallation'

            )->name('install');



            Route::post(

                '/measurement',

                'storeMeasurement'

            )->name('measurement');



            Route::post(

                '/remove',

                'removeInstallation'

            )->name('remove');



            Route::get(

                '/report',

                'report'

            )->name('report');



        });

    

    /*

    |--------------------------------------------------------------------------

    | VEHICLE / OPERATION

    |--------------------------------------------------------------------------

    */

    Route::get('/operations', [VehicleOperationController::class, 'index'])

    ->name('operations.index');

    

    Route::get('/vehicles/{vehicle}/operations/start', [VehicleOperationController::class, 'create'])

        ->name('vehicles.operations.start');

    

    Route::post('/vehicles/{vehicle}/operations/start', [VehicleOperationController::class, 'store'])

        ->name('vehicles.operations.store');

    

    Route::get('/operations/{operation}/close', [VehicleOperationController::class, 'close'])

        ->name('operations.close');

    

    Route::put('/operations/{operation}/close', [VehicleOperationController::class, 'finish'])

        ->name('operations.finish');





    /*

    |--------------------------------------------------------------------------

    | WORKSHOP / TIRES

    |--------------------------------------------------------------------------

    |

    | Aqui corrigimos o problema do 404.

    | Agora o update responde corretamente em:

    | PUT /workshop/tires/{tire}

    |

    */

    

    Route::prefix('workshop')

    ->name('workshop.')

    ->group(function () {



        Route::get(

            '/',

            [WorkshopController::class, 'index']

        )->name('index');



        Route::get(

            '/tires',

            [WorkshopTireController::class, 'index']

        )->name('tires.index');

        Route::get(
            '/tires/{tire}/history',
            [WorkshopTireController::class, 'history']
        )->name('tires.history');



        Route::post(

            '/tires/entries',

            [WorkshopTireController::class, 'storeEntry']

        )->name('tires.entries.store');

        Route::post(
            '/tires/entries/{entry}/cancel',
            [WorkshopTireController::class, 'cancelEntry']
        )->name('tires.entries.cancel');



        Route::post(
            '/tires/{tire}/retreads',
            [WorkshopTireController::class, 'storeRetread']
        )->name('tires.retreads.store');

        Route::post(
            '/tires/{tire}/measurements/{measurement}/cancel',
            [WorkshopTireController::class, 'cancelMeasurement']
        )->name('tires.measurements.cancel');

        Route::post(
            '/tires/{tire}/retreads/{retread}/cancel',
            [WorkshopTireController::class, 'cancelRetread']
        )->name('tires.retreads.cancel');



        Route::put(
            '/tires/{tire}',

            [WorkshopTireController::class, 'update']

        )->name('tires.update');



    });





    /*

    |--------------------------------------------------------------------------

    | PROCEDURES

    |--------------------------------------------------------------------------

    */



    Route::resource(

        'procedures',

        ProcedureController::class

    );



    /*

    |--------------------------------------------------------------------------

    | STOCK

    |--------------------------------------------------------------------------

    */



    Route::prefix('stock')

        ->name('stock.')

        ->controller(StockController::class)

        ->group(function () {



            Route::get(

                '/',

                'index'

            )->name('index');



            Route::post(

                '/categories',

                'storeCategory'

            )->name('categories.store');



            Route::post(

                '/items',

                'storeItem'

            )->name('items.store');



            Route::get(

                '/items/{item}',

                'showItem'

            )->name('items.show');



            Route::put(

                '/items/{item}',

                'updateItem'

            )->name('items.update');



            Route::post(

                '/movements',

                'storeMovement'

            )->name('movements.store');

            Route::post(

                '/movements/{movement}/cancel',

                'cancelMovement'

            )->name('movements.cancel');



        });



    /*

    |--------------------------------------------------------------------------

    | FUEL

    |--------------------------------------------------------------------------

    */



    Route::prefix('fuel')

        ->name('fuel.')

        ->controller(FuelTankController::class)

        ->group(function () {



            Route::get(

                '/',

                'index'

            )->name('tanks.index');



            Route::post(

                '/tanks',

                'store'

            )->name('tanks.store');



            Route::post(

                '/receipts',

                'storeReceipt'

            )->name('receipts.store');



            Route::post(

                '/fillings',

                'storeFilling'

            )->name('fillings.store');



            Route::put(

                '/tanks/{tank}',

                'update'

            )->name('tanks.update');



        });





    /*

    |--------------------------------------------------------------------------

    | CHECKLIST TEMPLATES

    |--------------------------------------------------------------------------

    */



    Route::middleware([

            'module:fleet',

        ])

        ->prefix('checklists')

        ->name('checklists.')

        ->group(function () {



            Route::get(

                '/',

                [ChecklistController::class, 'index']

            )->name('index');



            Route::post(

                '/items/{item}/toggle',

                [ChecklistController::class, 'toggleItem']

            )->name('items.toggle');



        });



    Route::post(

        '/checklists/items/toggle',

        [ChecklistController::class, 'toggleItem']

    )->name('checklists.items.toggle.legacy');





    /*

    |--------------------------------------------------------------------------

    | CHECKLIST EXECUTIONS

    |--------------------------------------------------------------------------

    */



    Route::prefix('checklist-executions')

        ->name('checklist-executions.')

        ->controller(ChecklistExecutionController::class)

        ->group(function () {



            Route::get(

                '/start/{vehicle}/{template}',

                'start'

            )->name('start');



            Route::post(

                '/store/{vehicle}/{template}',

                'store'

            )->name('store');



            Route::get(

                '/{execution}',

                'show'

            )->name('show');



        });





    /*

    |--------------------------------------------------------------------------

    | REPORTS

    |--------------------------------------------------------------------------

    */



    Route::middleware([

            'module:fleet',

            'profile:manager',

        ])

        ->prefix('reports')

        ->name('reports.')

        ->controller(ReportController::class)

        ->group(function () {



            Route::get(

                '/',

                'index'

            )->name('index');



            Route::get(

                '/tires',

                'tires'

            )->name('tires.index');



            Route::get(

                '/fuel',

                'fuel'

            )->name('fuel.index');



            Route::get(

                '/stock',

                'stock'

            )->name('stock.index');



            Route::get(

                '/vehicle-dossier',

                'vehicleDossier'

            )->name('vehicle-dossier.index');



            Route::get(

                '/stock/full',

                'stockFull'

            )->name('stock.full');



            Route::get(

                '/stock/export-pdf',

                'exportStockPdf'

            )->name('stock.export-pdf');



            Route::get(

                '/stock/export-excel',

                'exportStockExcel'

            )->name('stock.export-excel');



            Route::get(

                '/fuel/full',

                'fuelFull'

            )->name('fuel.full');



            Route::get(

                '/fuel/export-pdf',

                'exportFuelPdf'

            )->name('fuel.export-pdf');



            Route::get(

                '/fuel/export-excel',

                'exportFuelExcel'

            )->name('fuel.export-excel');



            Route::get(

                '/tires/full',

                'tiresFull'

            )->name('tires.full');



            Route::get(

                '/tires/export-pdf',

                'exportTiresPdf'

            )->name('tires.export.pdf');



            Route::get(

                '/tires/export-excel',

                'exportTiresExcel'

            )->name('tires.export.excel');



            Route::post(

                '/maintenance/export',

                'exportMaintenance'

            )->name('maintenance.export');



            Route::post(

                '/maintenance/export-excel',

                'exportMaintenanceExcel'

            )->name('maintenance.export.excel');



        });





    /*

    |--------------------------------------------------------------------------

    | ACCESS CONTROL

    |--------------------------------------------------------------------------

    */



    Route::middleware([

            'profile:admin,tenant-admin',

        ])

        ->prefix('access-control')

        ->name('access-control.')

        ->controller(AccessControlController::class)

        ->group(function () {



            Route::get(

                '/',

                'index'

            )->name('index');



            Route::post(

                '/store',

                'store'

            )->name('store');



            Route::put(

                '/{user}',

                'update'

            )->name('update');



        });



    /*

    |--------------------------------------------------------------------------

    | LOCATIONS / CIDADES

    |--------------------------------------------------------------------------

    */

    

    Route::prefix('locations')

        ->name('locations.')

        ->controller(LocationController::class)

        ->group(function () {

    

            Route::get(

                '/',

                'index'

            )->name('index');

    

            Route::post(

                '/',

                'store'

            )->name('store');

    

            Route::put(

                '/{location}',

                'update'

            )->name('update');

    

        });



    /*

    |--------------------------------------------------------------------------

    | API-LIKE INTERNAL ROUTES

    |--------------------------------------------------------------------------

    */



    Route::get(

        '/api/vehicles/{vehicle}',

        function (\App\Models\Vehicle $vehicle) {



            $vehicle->alerts =

                \App\Services\PreventiveService::getVehicleAlerts($vehicle);



            return response()->json([

                'vehicle' => $vehicle,

            ]);

        }

    )->name('api.vehicles.show');



});



require __DIR__ . '/auth.php';

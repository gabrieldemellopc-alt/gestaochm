<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Division;
use App\Models\DivisionUser;
use App\Models\UserDivisionAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AccessControlController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
    
        $divisions = Division::with('locations')
            ->when($authUser->id !== 1, function ($query) use ($authUser) {
                $query->where('tenant_id', $authUser->tenant_id);
            })
            ->orderBy('name')
            ->get();
    
        $users = User::with([
                'divisionAccesses.division',
                'divisionAccesses.location',
            ])
            ->when($authUser->id !== 1, function ($query) use ($authUser) {
                $query->where('tenant_id', $authUser->tenant_id);
            })
            ->orderBy('name')
            ->get();
    
        return view(
            'access-control.index',
            compact(
                'users',
                'divisions'
            )
        );
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
    
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
    
            'password' => [
                'required',
                'string',
                'min:6',
            ],
    
            'accesses' => [
                'required',
                'array',
                'min:1',
            ],
    
            'accesses.*.division_id' => [
                'required',
                'exists:divisions,id',
            ],
    
            'accesses.*.module' => [
                'required',
                'in:fleet',
            ],
    
            'accesses.*.profile' => [
                'required',
                'in:driver,mechanic,supervisor,manager',
            ],
    
            'accesses.*.location_id' => [
                'nullable',
                'exists:locations,id',
            ],
        ]);
    
        $authUser = auth()->user();
    
        $user = User::create([
    
            'tenant_id' =>
                $authUser->tenant_id,
    
            'name' =>
                $request->name,
    
            'email' =>
                $request->email,
            'active' => true,
            'password' =>
                Hash::make(
                    $request->password
                ),
    
        ]);
    
        $accesses =
            $request->input(
                'accesses',
                []
            );
    
        foreach ($accesses as $access) {
    
            if (
                empty($access['division_id']) ||
                empty($access['module']) ||
                empty($access['profile'])
            ) {
                continue;
            }
    
            $locationId =
                ! empty($access['location_id'])
                    ? $access['location_id']
                    : null;
    
            /*
            |--------------------------------------------------------------------------
            | DIVISION USER
            |--------------------------------------------------------------------------
            */
    
            DivisionUser::firstOrCreate([
    
                'user_id' =>
                    $user->id,
    
                'division_id' =>
                    $access['division_id'],
    
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | ACCESS CONTROL
            |--------------------------------------------------------------------------
            */
    
            UserDivisionAccess::firstOrCreate(
                [
                    'tenant_id' =>
                        $authUser->tenant_id,
            
                    'user_id' =>
                        $user->id,
            
                    'division_id' =>
                        $access['division_id'],
            
                    'location_id' =>
                        $access['location_id'],
            
                    'module' =>
                        $access['module'],
            
                    'profile' =>
                        $access['profile'],
                ],
                [
                    'active' =>
                        true,
                ]
            );
        }
    
        return back()->with(
            'success',
            'Usuário criado.'
        );
    }
    public function update(Request $request, User $user)
    {
        $authUser = auth()->user();
    
        if (
            $authUser->id !== 1 &&
            $user->tenant_id !== $authUser->tenant_id
        ) {
            abort(403);
        }
    
        $accesses = collect(
            $request->input('accesses', [])
        )
            ->map(function ($access) {
                $access['location_id'] =
                    ! empty($access['location_id'])
                        ? $access['location_id']
                        : null;
    
                return $access;
            })
            ->values()
            ->toArray();
    
        $request->merge([
            'accesses' => $accesses,
        ]);
    
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
    
            'password' => [
                'nullable',
                'string',
                'min:6',
            ],
    
            'accesses' => [
                'required',
                'array',
                'min:1',
            ],
    
            'accesses.*.division_id' => [
                'required',
                'exists:divisions,id',
            ],
    
            'accesses.*.module' => [
                'required',
                'in:fleet',
            ],
    
            'accesses.*.profile' => [
                'required',
                'in:driver,mechanic,supervisor,manager',
            ],
    
            'accesses.*.location_id' => [
                'nullable',
                'exists:locations,id',
            ],
        ]);
    
        $userData = [
            'name' =>
                $request->name,
            'active' =>
            $request->boolean('active'),
            'email' =>
                $request->email,
        ];
    
        if (
            $request->filled('password')
        ) {
            $userData['password'] =
                Hash::make(
                    $request->password
                );
        }
    
        $user->update($userData);
    
        /*
        |--------------------------------------------------------------------------
        | REMOVE ACESSOS ANTIGOS
        |--------------------------------------------------------------------------
        */
    
        UserDivisionAccess::where('user_id', $user->id)
            ->when($authUser->id !== 1, function ($query) use ($authUser) {
                $query->where('tenant_id', $authUser->tenant_id);
            })
            ->delete();
    
        /*
        |--------------------------------------------------------------------------
        | REMOVE VÍNCULOS ANTIGOS DE DIVISÃO
        |--------------------------------------------------------------------------
        */
    
        $divisionIds = collect($accesses)
            ->pluck('division_id')
            ->filter()
            ->unique()
            ->values();
    
        DivisionUser::where('user_id', $user->id)
            ->delete();
    
        /*
        |--------------------------------------------------------------------------
        | RECRIA ACESSOS
        |--------------------------------------------------------------------------
        */
    
        foreach ($accesses as $access) {
    
            if (
                empty($access['division_id']) ||
                empty($access['module']) ||
                empty($access['profile'])
            ) {
                continue;
            }
    
            DivisionUser::firstOrCreate([
    
                'user_id' =>
                    $user->id,
    
                'division_id' =>
                    $access['division_id'],
    
            ]);
    
            UserDivisionAccess::create([
    
                'tenant_id' =>
                    $user->tenant_id,
    
                'user_id' =>
                    $user->id,
    
                'division_id' =>
                    $access['division_id'],
    
                'location_id' =>
                    $access['location_id'],
    
                'module' =>
                    $access['module'],
    
                'profile' =>
                    $access['profile'],
    
                'active' =>
                    true,
    
            ]);
        }
    
        return back()->with(
            'success',
            'Usuário atualizado.'
        );
    }
}
<?php

use App\Models\UserDivisionAccess;

if (! function_exists('userHasModule'))
{
    function userHasModule($module)
    {
        if(! auth()->check())
        {
            return false;
        }

        return UserDivisionAccess::where(

            'user_id',
            auth()->id()

        )
        ->where(

            'division_id',
            session('active_division_id')

        )
        ->where(

            'module',
            $module

        )
        ->where(

            'active',
            true

        )
        ->exists();
    }
}

if (! function_exists('userHasProfile'))
{
    function userHasProfile($profile)
    {
        if(! auth()->check())
        {
            return false;
        }

        return UserDivisionAccess::where(

            'user_id',
            auth()->id()

        )
        ->where(

            'division_id',
            session('active_division_id')

        )
        ->where(

            'profile',
            $profile

        )
        ->where(

            'active',
            true

        )
        ->exists();
    }
}

if (! function_exists('userHasRole'))
{
    function userHasRole($role)
    {
        if(! auth()->check())
        {
            return false;
        }

        return UserDivisionAccess::where(

            'user_id',
            auth()->id()

        )
        ->where(

            'division_id',
            session('active_division_id')

        )
        ->where(

            'profile',
            $role

        )
        ->where(

            'active',
            true

        )
        ->exists();
    }
}

if (! function_exists('userCanAccessDivision'))
{
    function userCanAccessDivision($divisionId)
    {
        if(! auth()->check())
        {
            return false;
        }

        return UserDivisionAccess::where(

            'user_id',
            auth()->id()

        )
        ->where(

            'division_id',
            $divisionId

        )
        ->where(

            'active',
            true

        )
        ->exists();
    }
}

if (! function_exists('chm_icon'))
{
    function chm_icon(?string $name): string
    {
        $aliases = [
            'arrow-right-left' => 'arrow-left-right', 'bar-chart-3' => 'bar-chart',
            'building-2' => 'buildings', 'calendar-days' => 'calendar-week',
            'check-circle-2' => 'check-circle', 'circle-alert' => 'exclamation-circle',
            'circle-check' => 'check-circle', 'circle-check-big' => 'check-circle',
            'circle-dot' => 'circle', 'clock-3' => 'clock', 'edit-3' => 'pencil',
            'external-link' => 'box-arrow-up-right', 'file-text' => 'file-earmark-text',
            'fuel' => 'fuel-pump', 'gauge' => 'speedometer2', 'history' => 'clock-history',
            'map-pin' => 'geo-alt', 'plus' => 'plus-lg', 'refresh-cw' => 'arrow-clockwise',
            'rotate-ccw' => 'arrow-counterclockwise', 'save' => 'floppy',
            'search-x' => 'search', 'settings-2' => 'sliders', 'trash-2' => 'trash',
            'triangle-alert' => 'exclamation-triangle', 'user-round' => 'person',
            'users' => 'people', 'wallet' => 'wallet2', 'wrench' => 'wrench-adjustable',
            'x' => 'x-lg',
        ];

        return 'bi bi-'.($aliases[$name] ?? $name ?: 'question-circle');
    }
}

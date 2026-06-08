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
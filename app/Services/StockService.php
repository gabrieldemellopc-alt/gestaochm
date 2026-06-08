<?php

namespace App\Services;

class StockService
{
    public static function getStatus($item)
    {
        /*
        |--------------------------------------------------------------------------
        | Sem mínimo
        |--------------------------------------------------------------------------
        */

        if(
            !$item->minimum_quantity ||
            $item->minimum_quantity <= 0
        ){
            return 'ok';
        }

        /*
        |--------------------------------------------------------------------------
        | DANGER
        |--------------------------------------------------------------------------
        */

        if(
            $item->quantity <=
            $item->minimum_quantity
        ){
            return 'danger';
        }

        /*
        |--------------------------------------------------------------------------
        | WARNING
        |--------------------------------------------------------------------------
        */

        $warningLimit =
            $item->minimum_quantity * 1.2;

        if(
            $item->quantity <=
            $warningLimit
        ){
            return 'warning';
        }

        return 'ok';
    }
}
<?php
namespace App\Support;

use Carbon\Carbon;

class ConsistencyAlertPresenter
{
    public function consumption($value): string { $value=(float)$value; return number_format(abs($value)<.005?0:$value,2,',','.').' km/L'; }
    public function distance($value): string { $value=(float)$value; return number_format($value,abs($value-round($value))<.05?0:1,',','.').' km'; }
    public function reading($value, string $type='km'): string { return number_format((float)$value,$type==='hours'?2:0,',','.').($type==='hours'?' hr':' km'); }
    public function liters($value): string { return number_format((float)$value,2,',','.').' L'; }
    public function money($value): string { return 'R$ '.number_format((float)$value,2,',','.'); }
    public function date($value): string { if(!$value)return ''; $date=Carbon::parse($value); return $date->format('H:i:s')==='00:00:00'?$date->format('d/m/Y'):$date->format('d/m/Y H:i'); }
}

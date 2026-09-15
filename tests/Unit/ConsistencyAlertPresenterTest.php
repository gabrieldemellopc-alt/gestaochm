<?php
namespace Tests\Unit;
use App\Support\ConsistencyAlertPresenter;
use Tests\TestCase;
class ConsistencyAlertPresenterTest extends TestCase
{
 public function test_formats_consumption_distance_and_dates_for_pt_br():void{$p=new ConsistencyAlertPresenter();$this->assertSame('24,72 km/L',$p->consumption(24.7222));$this->assertSame('1.502,49 km/L',$p->consumption(1502.486));$this->assertSame('0,00 km/L',$p->consumption(-.00015));$this->assertSame('221.181 km',$p->distance(221181));$this->assertSame('27/07/2026',$p->date('2026-07-27 00:00:00'));$this->assertSame('04/09/2026 07:58',$p->date('2026-09-04 07:58:00'));}
}

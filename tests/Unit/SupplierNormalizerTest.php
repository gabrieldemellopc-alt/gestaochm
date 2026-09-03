<?php
namespace Tests\Unit;
use App\Services\SupplierNormalizer; use Tests\TestCase;
class SupplierNormalizerTest extends TestCase { public function test_documents_are_normalized_validated_and_formatted():void{$n=app(SupplierNormalizer::class);$this->assertSame('11222333000181',$n->normalizeDocument('11.222.333/0001-81'));$this->assertTrue($n->validateCnpj('11.222.333/0001-81'));$this->assertFalse($n->validateCnpj('11.222.333/0001-82'));$this->assertTrue($n->validateCpf('529.982.247-25'));$this->assertSame('11.222.333/0001-81',$n->formatDocument('11222333000181'));} public function test_name_normalization_is_predictable():void{$this->assertSame('tocantins borrachas',$n=app(SupplierNormalizer::class)->normalizeName('Tocantins Borrachas LTDA.'));} }

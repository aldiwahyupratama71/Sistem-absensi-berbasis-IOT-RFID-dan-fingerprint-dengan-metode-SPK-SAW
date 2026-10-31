<?php

namespace Tests\Unit;

use App\Services\SawCalculator;
use PHPUnit\Framework\TestCase;

class SawCalculatorTest extends TestCase
{
    protected SawCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SawCalculator();
    }

    public function test_c1_0_absent_tanpa_keterangan()
    {
        $this->assertEquals(100.0, $this->calculator->c1(0));
    }

    public function test_c1_1_absent_tanpa_keterangan()
    {
        $this->assertEquals(40.0, $this->calculator->c1(1));
    }

    public function test_c1_2_absent_tanpa_keterangan_cannot_be_negative()
    {
        $this->assertEquals(0.0, $this->calculator->c1(2)); // 100 - 120 = -20 -> 0
    }

    public function test_c2_0_telat()
    {
        $this->assertEquals(100.0, $this->calculator->c2(0, 0));
    }

    public function test_c2_2_telat_1_10_and_1_telat_lebih_10()
    {
        // 100 - (2*10 + 1*40) = 100 - 60 = 40
        $this->assertEquals(40.0, $this->calculator->c2(2, 1));
    }

    public function test_c2_cannot_be_negative()
    {
        // 100 - (0 + 3*40) = 100 - 120 = -20 -> 0
        $this->assertEquals(0.0, $this->calculator->c2(0, 3));
    }

    public function test_c3_0_fingerprint()
    {
        // 20 hadir, 0 fingerprint -> 20/20 * 100 = 100
        $this->assertEquals(100.0, $this->calculator->c3(20, 0));
    }

    public function test_c3_20_hadir_3_fingerprint()
    {
        // (20 - 3) / 20 * 100 = 17/20 * 100 = 85
        $this->assertEquals(85.0, $this->calculator->c3(20, 3));
    }

    public function test_c3_hadir_0()
    {
        $this->assertEquals(0.0, $this->calculator->c3(0, 0));
    }

    public function test_combination_final_score()
    {
        // C1 100, C2 90, C3 95
        // bobot: 0.5, 0.35, 0.15
        // (1*0.5 + 0.9*0.35 + 0.95*0.15) = 0.5 + 0.315 + 0.1425 = 0.9575 -> 95.75
        $normalized = [
            'C1' => 1.00,
            'C2' => 0.90,
            'C3' => 0.95
        ];
        $weights = [
            'C1' => 0.50,
            'C2' => 0.35,
            'C3' => 0.15
        ];

        $this->assertEquals(95.75, $this->calculator->finalScore($normalized, $weights));
    }

    public function test_batas_kategori()
    {
        $this->assertEquals('Positif', $this->calculator->categorize(95.75));
        $this->assertEquals('Positif', $this->calculator->categorize(90.00));
        
        $this->assertEquals('Perlu Pertimbangan', $this->calculator->categorize(89.99));
        $this->assertEquals('Perlu Pertimbangan', $this->calculator->categorize(80.00));
        
        $this->assertEquals('Perlu Perhatian', $this->calculator->categorize(79.99));
        $this->assertEquals('Perlu Perhatian', $this->calculator->categorize(70.00));
        
        $this->assertEquals('Evaluasi Khusus', $this->calculator->categorize(69.99));
        $this->assertEquals('Evaluasi Khusus', $this->calculator->categorize(0.00));
    }
}

<?php

namespace Database\Factories;

use App\Models\IotDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<IotDevice>
 */
class IotDeviceFactory extends Factory
{
    protected $model = IotDevice::class;

    public function definition(): array
    {
        $plainKey = Str::random(48);

        return [
            'nama'           => 'Device ' . $this->faker->word(),
            'lokasi'         => $this->faker->randomElement(['Pintu Utama', 'Lantai 2', 'Ruang Server']),
            'api_key_hash'   => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
            'last_seen_at'   => null,
        ];
    }

    /** Device yang baru saja aktif (online) */
    public function online(): static
    {
        return $this->state(['last_seen_at' => now()]);
    }
}

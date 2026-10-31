<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'nip'         => $this->faker->unique()->numerify('NIP######'),
            'nama'        => $this->faker->name(),
            'departemen'  => $this->faker->randomElement(['IT', 'HRD', 'Keuangan', 'Operasional']),
            'jabatan'     => $this->faker->jobTitle(),
            'status'      => 'aktif',
            'status_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['status' => 'nonaktif', 'status_aktif' => false]);
    }
}

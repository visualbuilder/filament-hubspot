<?php

namespace Visualbuilder\FilamentHubspot\Database\Factories;


use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \Visualbuilder\FilamentHubspot\Models\Lead::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {

        return [
            'salutation'      => $this->faker->randomElement(config('company.salutations')),
            'first_name'      => $this->faker->firstName(),
            'last_name'       => $this->faker->lastName(),
            'company'         => $this->faker->company(),
            'email'           => $this->faker->email(),
            'website'         => $this->faker->url(),
            'owner_id'        => null,
            'owner_type'      => null,
            'created_at'      => now(),
            'updated_at'      => now(),
            'deleted_at'      => null,
        ];
    }
}

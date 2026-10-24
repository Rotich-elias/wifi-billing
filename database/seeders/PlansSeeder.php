<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => '1 Hour',
                'type' => 'daily',
                'duration_hours' => 1,
                'price' => 20.00,
                'bandwidth_up' => '2M',
                'bandwidth_down' => '2M',
                'idle_timeout' => 300,
                'description' => 'Quick 1-hour browsing',
            ],
            [
                'name' => '3 Hours',
                'type' => 'daily',
                'duration_hours' => 3,
                'price' => 50.00,
                'bandwidth_up' => '2M',
                'bandwidth_down' => '2M',
                'idle_timeout' => 300,
                'description' => '3-hour internet access',
            ],
            [
                'name' => 'Day Pass',
                'type' => 'daily',
                'duration_hours' => 24,
                'price' => 100.00,
                'bandwidth_up' => '5M',
                'bandwidth_down' => '5M',
                'idle_timeout' => 600,
                'description' => 'Unlimited 24-hour access',
            ],
            [
                'name' => 'Weekly',
                'type' => 'weekly',
                'duration_hours' => 168,
                'price' => 500.00,
                'bandwidth_up' => '5M',
                'bandwidth_down' => '5M',
                'idle_timeout' => 900,
                'description' => '7-day unlimited access',
            ],
            [
                'name' => 'Monthly',
                'type' => 'monthly',
                'duration_hours' => 720,
                'price' => 1500.00,
                'bandwidth_up' => '10M',
                'bandwidth_down' => '10M',
                'idle_timeout' => 1800,
                'description' => '30-day high-speed access',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}

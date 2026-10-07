<?php

namespace App\Modules\Users\Database\Seeders;

use App\Modules\Users\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpecializationsSeeder extends Seeder
{
    public function run(): void
    {
        $specializations = [
            'Dimagrimento',
            'Aumento massa',
            'Posturale',
            'Crossfit',
            'Calisthenics',
            'Yoga',
            'Pilates',
            'Riabilitazione',
            'Preparazione atletica',
            'Functional training',
            'Senior fitness',
            'HIIT',
            'Bodybuilding',
            'Running',
            'Nutrizione sportiva',
            'Stretching e mobilità',
            'Boxe e arti marziali',
            'Nuoto',
            'Ciclismo',
            'Powerlifting',
        ];

        foreach ($specializations as $name) {
            Specialization::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_custom' => false],
            );
        }
    }
}

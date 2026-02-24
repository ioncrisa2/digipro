<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'appraisal',
            'valuation',
            'properti',
            'regulasi',
            'banking',
            'risk',
            'data',
            'workflow',
            'market',
            'insight',
        ];

        foreach ($tags as $tag) {
            $slug = Str::slug($tag);
            Tag::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::title($tag),
                    'is_active' => true,
                ]
            );
        }
    }
}

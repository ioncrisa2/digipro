<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Market Insight', 'description' => 'Wawasan pasar dan tren valuasi properti.'],
            ['name' => 'Regulasi', 'description' => 'Update aturan dan kebijakan terkait penilaian.'],
            ['name' => 'Studi Kasus', 'description' => 'Pembahasan kasus dan hasil penilaian.'],
            ['name' => 'Tips Appraisal', 'description' => 'Praktik terbaik untuk appraisal.'],
            ['name' => 'Teknologi', 'description' => 'Digitalisasi dan tools untuk valuation.'],
        ];

        foreach ($categories as $index => $category) {
            $slug = Str::slug($category['name']);
            ArticleCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'show_in_nav' => $index < 4,
                ]
            );
        }
    }
}

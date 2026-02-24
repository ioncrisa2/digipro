<?php

namespace App\Filament\Pages;

use Filament\Forms;
use App\Models\Regency;
use App\Models\Province;
use Filament\Forms\Form;
use Filament\Pages\Page;
use App\Models\GuidelineSet;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use App\Filament\Clusters\RefGuidelines;
use Filament\Notifications\Notification;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;

class IkkByProvince extends Page
{
    use InteractsWithActions, InteractsWithForms;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationLabel = 'Indek Kemahalan Kontruksi per Provinsi';
    protected static ?int $navigationSort = 10;

    // kalau kamu mau tampil di group tertentu:
    protected static ?string $navigationGroup = 'Ref Guidelines';

    protected static ?string $cluster = RefGuidelines::class;
    protected static bool $shouldRegisterNavigation = false;


    protected static string $view = 'filament.pages.ikk-by-province';

    public ?int $guideline_set_id = null;
    public ?int $year = null;
    public ?string $province_id = null;

    /** @var array<int, array{region_code:string, regency_name:string, ikk_value:float|int|string|null}> */
    public array $items = [];

    public function mount(): void
    {
        $active = GuidelineSet::query()->where('is_active', true)->first();

        $this->guideline_set_id = (int) (request()->query('guideline_set_id') ?? $active?->id);
        $this->year = (int) (request()->query('year') ?? $active?->year ?? now()->format('Y'));
        $this->province_id = (string) (request()->query('province_id') ?? $this->province_id);

        $this->reloadItems();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Parameter')
                ->schema([
                    Forms\Components\Select::make('guideline_set_id')
                        ->label('Guideline Set')
                        ->options(fn() => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state) {
                            $this->year = GuidelineSet::find($state)?->year;
                            $this->reloadItems();
                        }),

                    Forms\Components\TextInput::make('year')
                        ->numeric()
                        ->required()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->reactive()
                        ->afterStateUpdated(fn() => $this->reloadItems()),

                    Forms\Components\Select::make('province_id')
                        ->label('Provinsi')
                        ->options(fn() => Province::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn() => $this->reloadItems()),
                ])
                ->columns(3),

            Forms\Components\Section::make('Daftar Kab/Kota')
                ->visible(fn() => filled($this->province_id) && filled($this->guideline_set_id) && filled($this->year) && count($this->items))
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Forms\Components\TextInput::make('region_code')
                                ->label('Kode')
                                ->disabled()
                                ->dehydrated()
                                ->required(),

                            Forms\Components\TextInput::make('regency_name')
                                ->label('Kab/Kota')
                                ->disabled()
                                ->dehydrated()
                                ->required(),

                            Forms\Components\TextInput::make('ikk_value')
                                ->label('IKK')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->visible(fn() => filled($this->province_id) && filled($this->guideline_set_id) && filled($this->year)),
                ]),
        ])->statePath('');
    }

    public function resetIkk(): void
    {
        foreach ($this->items as $i => $row) {
            $this->items[$i]['ikk_value'] = null;
        }

        $this->form->fill([
            'items' => $this->items,
        ]);

        Notification::make()
            ->title('Nilai IKK dikosongkan (belum tersimpan)')
            ->success()
            ->send();
    }

    public function resetProvince(): void
    {
        $this->province_id = null;
        $this->items = [];

        $this->form->fill([
            'province_id' => null,
            'items' => [],
        ]);
    }

    public function reloadItems(): void
    {
        if (blank($this->province_id) || blank($this->guideline_set_id) || blank($this->year)) {
            $this->items = [];
            return;
        }

        $regencies = Regency::query()
            ->where('province_id', $this->province_id)
            ->orderByRaw('CAST(id AS UNSIGNED) ASC')
            ->get(['id', 'name']);

        $existing = DB::table('ref_construction_cost_index')
            ->where('guideline_set_id', $this->guideline_set_id)
            ->where('year', $this->year)
            ->whereIn('region_code', $regencies->pluck('id')->all())
            ->pluck('ikk_value', 'region_code'); // [ '1202' => 0.812, ... ]

        $this->items = $regencies->map(fn($r) => [
            'region_code' => (string) $r->id,
            'regency_name' => (string) $r->name,
            'ikk_value' => $existing[(string) $r->id] ?? null,
        ])->all();
    }

    public function save(): void
    {
        $this->validate([
            'guideline_set_id' => ['required', 'integer'],
            'year' => ['required', 'integer'],
            'province_id' => ['required', 'string'],
            'items' => ['array'],
            'items.*.region_code' => ['required', 'string'],
            'items.*.regency_name' => ['required', 'string'],
            'items.*.ikk_value' => ['required', 'numeric', 'min:0'],
        ]);

        $now = now();

        DB::transaction(function () use ($now) {
            foreach ($this->items as $row) {
                DB::table('ref_construction_cost_index')->updateOrInsert(
                    [
                        'guideline_set_id' => $this->guideline_set_id,
                        'year' => $this->year,
                        'region_code' => $row['region_code'],
                    ],
                    [
                        'region_name' => $row['regency_name'],
                        'ikk_value' => $row['ikk_value'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        });

        Notification::make()
            ->title('IKK berhasil disimpan')
            ->success()
            ->send();
    }
}

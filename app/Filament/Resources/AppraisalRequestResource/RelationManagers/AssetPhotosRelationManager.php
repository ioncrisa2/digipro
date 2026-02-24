<?php

namespace App\Filament\Resources\AppraisalRequestResource\RelationManagers;

use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAssetFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'assetFiles';

    protected static ?string $title = 'Foto';

    protected static ?string $recordTitleAttribute = 'original_name';

    /**
     * @var array<int, int>|null
     */
    private ?array $assetOrderMap = null;

    public function table(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('appraisal_asset_id')
                    ->label('Aset')
                    ->titlePrefixedWithLabel(false)
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn (AppraisalAssetFile $record): string => $this->assetGroupTitle($record)),
            ])
            ->defaultGroup('appraisal_asset_id')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->recordClasses('rounded-2xl shadow-md')
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('path')
                        ->label('Preview')
                        ->getStateUsing(fn (AppraisalAssetFile $record) => Storage::disk('public')->url($record->path))
                        ->height(200)
                        ->width('100%')
                        ->inline()
                        ->extraImgAttributes([
                            'class' => 'w-full rounded-lg object-cover',
                            'loading' => 'lazy',
                        ]),

                    Tables\Columns\TextColumn::make('original_name')
                        ->label('Nama File')
                        ->weight('medium')
                        ->wrap()
                        ->lineClamp(2)
                        ->searchable()
                        ->description(fn (AppraisalAssetFile $record) => $this->labelType($record->type) . ' • ' . $this->formatBytes($record->size)),

                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Uploaded')
                        ->dateTime('d M Y H:i')
                        ->color('gray')
                        ->size('sm')
                        ->sortable(),
                ])->space(2),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (AppraisalAssetFile $record) => Storage::disk('public')->url($record->path))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    protected function getTableQuery(): Builder
    {
        return $this->getRelationship()
            ->getQuery()
            ->with('appraisalAsset')
            ->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])
            ->orderBy('appraisal_asset_files.appraisal_asset_id')
            ->orderBy('appraisal_asset_files.created_at', 'desc');
    }

    private function assetGroupTitle(AppraisalAssetFile $record): string
    {
        $asset = $record->appraisalAsset;

        if (! $asset) {
            return 'Aset tidak ditemukan';
        }

        $assetOrder = $this->resolveAssetOrder($asset->id);
        $assetLabel = $this->assetTypeLabel($asset->asset_type);
        $address = trim((string) ($asset->address ?? ''));

        $title = $assetOrder ? "Aset #{$assetOrder}" : "Aset ID #{$asset->id}";
        $parts = [$title];

        if ($assetLabel !== '') {
            $parts[] = $assetLabel;
        }

        if ($address !== '') {
            $parts[] = Str::limit($address, 70);
        }

        return implode(' - ', $parts);
    }

    private function resolveAssetOrder(?int $assetId): ?int
    {
        if (! $assetId) {
            return null;
        }

        if ($this->assetOrderMap === null) {
            $assetIds = $this->getOwnerRecord()
                ->assets()
                ->orderBy('id')
                ->pluck('id')
                ->values();

            $this->assetOrderMap = $assetIds
                ->flip()
                ->map(fn (int $index): int => $index + 1)
                ->all();
        }

        return $this->assetOrderMap[$assetId] ?? null;
    }

    private function assetTypeLabel(?string $type): string
    {
        if (! $type) {
            return '';
        }

        $enumLabel = AssetTypeEnum::tryFrom($type)?->label();
        if ($enumLabel) {
            return $enumLabel;
        }

        return Str::of($type)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function labelType(?string $type): string
    {
        $t = (string) $type;

        $map = [
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
        ];

        return $map[$t] ?? ($t !== '' ? $t : '-');
    }

    private function formatBytes($bytes): string
    {
        if (!is_numeric($bytes) || (float) $bytes <= 0) {
            return '0 B';
        }

        $n = (float) $bytes;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $idx = (int) floor(log($n, 1024));
        $idx = min($idx, count($units) - 1);
        $val = $n / pow(1024, $idx);

        return sprintf('%s %s', number_format($val, $idx === 0 ? 0 : 2), $units[$idx]);
    }
}

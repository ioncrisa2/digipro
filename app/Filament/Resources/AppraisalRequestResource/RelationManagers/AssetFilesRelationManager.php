<?php

namespace App\Filament\Resources\AppraisalRequestResource\RelationManagers;

use App\Models\AppraisalAssetFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AssetFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'assetFiles';

    protected static ?string $title = 'Dokumen & Foto';

    protected static ?string $recordTitleAttribute = 'original_name';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (?string $state) => $this->labelType($state))
                    ->badge(),

                Tables\Columns\TextColumn::make('original_name')
                    ->label('Nama File')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('mime')
                    ->label('Mime')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('size')
                    ->label('Ukuran')
                    ->formatStateUsing(fn ($state) => $this->formatBytes($state)),

                Tables\Columns\TextColumn::make('appraisal_asset_id')
                    ->label('Asset ID')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (AppraisalAssetFile $record) {
                        return Storage::disk('public')->download(
                            $record->path,
                            $record->original_name ?? basename($record->path),
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    private function labelType(?string $type): string
    {
        $t = (string) $type;

        $map = [
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
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

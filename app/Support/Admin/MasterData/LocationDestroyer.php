<?php

namespace App\Support\Admin\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class LocationDestroyer
{
    public function destroy(Model $record, string $routeName, string $label): RedirectResponse
    {
        try {
            $record->delete();
        } catch (QueryException) {
            return redirect()
                ->route($routeName)
                ->with('error', $label . ' tidak bisa dihapus karena masih dipakai data turunan.');
        }

        return redirect()
            ->route($routeName)
            ->with('success', $label . ' berhasil dihapus.');
    }
}

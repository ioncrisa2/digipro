<?php

use App\Support\ReviewerBtbCatalog;

it('registers the BTB worksheet templates from the workbook', function () {
    expect(array_keys(ReviewerBtbCatalog::templates()))->toBe([
        'rumah_mewah',
        'rumah_menengah',
        'rumah_sederhana',
        'semi_permanen',
        'gudang',
        'low_rise_building',
    ]);

    expect(ReviewerBtbCatalog::template('rumah_menengah'))->toMatchArray([
        'sheet_name' => 'R. Menengah',
        'mappi_building_type' => 'BANGUNAN_RUMAH_TINGGAL',
        'mappi_building_class' => 'MENENGAH',
    ]);
});

it('marks land only usages as not requiring BTB', function () {
    expect(ReviewerBtbCatalog::requiresBtb('tanah_kosong'))->toBeFalse();
    expect(ReviewerBtbCatalog::requiresBtb('tanah_kebun'))->toBeFalse();
    expect(ReviewerBtbCatalog::requiresBtb('sawah'))->toBeFalse();
});

it('resolves BTB templates from usage and building class aliases', function () {
    expect(ReviewerBtbCatalog::resolveTemplateKey('rumah_tinggal', 'Menengah'))->toBe('rumah_menengah');
    expect(ReviewerBtbCatalog::resolveTemplateKey('rumah_tinggal', 'mewah'))->toBe('rumah_mewah');
    expect(ReviewerBtbCatalog::resolveTemplateKey('kantor', null))->toBe('low_rise_building');
    expect(ReviewerBtbCatalog::resolveTemplateKey('gudang', 'Semi Permanen'))->toBe('semi_permanen');
    expect(ReviewerBtbCatalog::resolveTemplateKey('tanah_kosong', 'Mewah'))->toBeNull();
});

it('keeps the workbook section structure available for the BTB engine', function () {
    expect(ReviewerBtbCatalog::sections())->toHaveKeys([
        'hard_cost',
        'indirect_cost',
        'depreciation',
    ]);

    expect(ReviewerBtbCatalog::lineItems())->toHaveKeys([
        'foundation',
        'structure',
        'roof_frame',
        'roof_cover',
        'ceiling',
        'wall',
        'door_window',
        'floor_finish',
        'utilities',
        'hard_cost_total_ikk_floor_index',
        'professional_fee',
        'indirect_cost_total',
        'depreciated_brb_total',
    ]);
});

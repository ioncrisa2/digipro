// composables/features/appraisal/useAssetForm.js
import { reactive, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useAssetForm({ props, emit }) {
    // Asset form state
    const asset = reactive({
        type: '',
        landArea: '',
        buildingArea: '',
        floors: '',
        renovationYear: '',
        province: '',
        regency: '',
        district: '',
        village: '',
        address: '',

        // location precision (choose one):
        coordinatesLat: '',
        coordinatesLng: '',
        mapsLink: '',

        // docs
        docPbb: null,            // wajib
        docImb: null,            // opsional bila ada bangunan
        docCerts: [],            // wajib

        // photos 3 section (wajib masing-masing minimal 1)
        photosAccessRoad: [],
        photosFront: [],
        photosInterior: [],
    });

    // Check if editing
    const isEditing = computed(() => !!props.initialAsset);

    // Check if land only
    const isLandOnly = computed(() => asset.type === 'land');

    // ============================================
    // POPULATE FORM WHEN EDITING
    // ============================================
    const populateFromInitialAsset = () => {
        if (!props.initialAsset) return;

        const initial = props.initialAsset;

        // Basic info
        asset.type = initial.type || '';
        asset.landArea = initial.landArea || '';
        asset.buildingArea = initial.buildingArea || '';
        asset.floors = initial.floors || '';
        asset.renovationYear = initial.renovationYear || '';

        // Location
        asset.province = initial.province || '';
        asset.regency = initial.regency || '';
        asset.district = initial.district || '';
        asset.village = initial.village || '';
        asset.address = initial.address || '';

        asset.coordinatesLat = initial.coordinatesLat || '';
        asset.coordinatesLng = initial.coordinatesLng || '';
        asset.mapsLink = initial.mapsLink || '';

        // Documents
        asset.docPbb = initial.docPbb || null;
        asset.docImb = initial.docImb || null;
        asset.docCerts = initial.docCerts || [];

        // Photos
        asset.photosAccessRoad = initial.photosAccessRoad || [];
        asset.photosFront = initial.photosFront || [];
        asset.photosInterior = initial.photosInterior || [];
    };

    onMounted(() => {
        populateFromInitialAsset();
    });

    watch(
        () => props.initialAsset,
        () => {
            populateFromInitialAsset();
        },
        { deep: true }
    );

    // ============================================
    // LOCATION CASCADE HANDLERS
    // ============================================
    const loadRegencies = (provinceId) => {
        if (!provinceId) return;

        router.get(
            route(props.locationRouteName, { province_id: provinceId }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['regencies'],
            }
        );
    };

    const loadDistricts = (regencyId) => {
        if (!regencyId) return;

        router.get(
            route(props.locationRouteName, {
                province_id: asset.province,
                regency_id: regencyId,
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['districts'],
            }
        );
    };

    const loadVillages = (districtId) => {
        if (!districtId) return;

        router.get(
            route(props.locationRouteName, {
                province_id: asset.province,
                regency_id: asset.regency,
                district_id: districtId,
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['villages'],
            }
        );
    };

    const onProvinceChange = (provinceId) => {
        asset.province = provinceId;

        // Reset dependent fields
        asset.regency = '';
        asset.district = '';
        asset.village = '';

        loadRegencies(provinceId);
    };

    const onRegencyChange = (regencyId) => {
        asset.regency = regencyId;

        // Reset dependent fields
        asset.district = '';
        asset.village = '';

        loadDistricts(regencyId);
    };

    const onDistrictChange = (districtId) => {
        asset.district = districtId;

        // Reset dependent field
        asset.village = '';

        loadVillages(districtId);
    };

    // ============================================
    // VALIDATION
    // ============================================
    const hasValidCoords = () => {
        if (!asset.coordinatesLat || !asset.coordinatesLng) return false;
        const lat = Number.parseFloat(String(asset.coordinatesLat).trim());
        const lng = Number.parseFloat(String(asset.coordinatesLng).trim());
        if (Number.isNaN(lat) || Number.isNaN(lng)) return false;
        // basic bounds
        if (lat < -90 || lat > 90) return false;
        if (lng < -180 || lng > 180) return false;
        return true;
    };

    const hasMapsLink = () => {
        return !!String(asset.mapsLink || '').trim();
    };

    const validateAsset = () => {
        const errors = [];

        // Required fields
        if (!asset.type) errors.push('Pilih tipe properti');
        if (!asset.landArea) errors.push('Masukkan luas tanah');
        if (!asset.province) errors.push('Pilih provinsi');
        if (!asset.regency) errors.push('Pilih kabupaten/kota');
        if (!asset.address) errors.push('Masukkan alamat lengkap');

        // Location precision is mandatory
        if (!hasValidCoords() && !hasMapsLink()) {
            errors.push('Isi lokasi presisi: koordinat (lat/lng) atau link Google Maps');
        }

        // Building-specific validation
        if (!isLandOnly.value) {
            if (!asset.buildingArea) errors.push('Masukkan luas bangunan');
            if (!asset.floors) errors.push('Masukkan jumlah lantai');
            // IMB/PBG bersifat opsional
        }

        // Document validation
        if (!asset.docPbb) errors.push('Upload PBB tahun terakhir');
        if (!asset.docCerts || asset.docCerts.length === 0) {
            errors.push('Upload minimal 1 sertifikat');
        }

        // Photo sections validation
        if (!asset.photosAccessRoad || asset.photosAccessRoad.length === 0) {
            errors.push('Upload foto: Nampak Akses Jalan Property');
        }
        if (!asset.photosFront || asset.photosFront.length === 0) {
            errors.push('Upload foto: Nampak Depan Aset');
        }
        if (!asset.photosInterior || asset.photosInterior.length === 0) {
            errors.push('Upload foto: Nampak Dalam Aset');
        }

        return {
            valid: errors.length === 0,
            errors,
        };
    };

    // ============================================
    // SAVE HANDLER
    // ============================================
    const saveAsset = () => {
        const validation = validateAsset();

        if (!validation.valid) {
            alert(`Mohon lengkapi:\n- ${validation.errors.join('\n- ')}`);
            return;
        }

        // Get location names from props for display
        const provinceName = props.provinces.find((p) => p.id === asset.province)?.name || '';
        const regencyName = props.regencies.find((r) => r.id === asset.regency)?.name || '';
        const districtName = props.districts.find((d) => d.id === asset.district)?.name || '';
        const villageName = props.villages.find((v) => v.id === asset.village)?.name || '';

        // Get type label
        const typeLabels = {
            house: 'Rumah Tinggal',
            land: 'Tanah Kosong',
            shophouse: 'Ruko / Rukan',
            warehouse: 'Gudang / Pabrik',
        };

        const coordinates = hasValidCoords()
            ? {
                  lat: Number.parseFloat(String(asset.coordinatesLat).trim()),
                  lng: Number.parseFloat(String(asset.coordinatesLng).trim()),
              }
            : '';

        const assetData = {
            // Copy all asset data
            ...asset,

            // Derived fields for store submission
            coordinates,

            // Add display names for UI
            typeLabel: typeLabels[asset.type] || asset.type,
            provinceName,
            regencyName,
            districtName,
            villageName,
        };

        emit('save', assetData);
    };

    // ============================================
    // AUTO-CLEAR BUILDING FIELDS WHEN LAND
    // ============================================
    watch(isLandOnly, (newVal) => {
        if (newVal) {
            asset.buildingArea = '';
            asset.floors = '';
            asset.renovationYear = '';
            asset.docImb = null;
        }
    });

    return {
        asset,
        isLandOnly,
        isEditing,
        onProvinceChange,
        onRegencyChange,
        onDistrictChange,
        saveAsset,
    };
}

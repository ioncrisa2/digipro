<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

const props = defineProps({
  mode: {
    type: String,
    required: true,
  },
  requestRecord: {
    type: Object,
    required: true,
  },
  record: {
    type: Object,
    required: true,
  },
  assetTypeOptions: {
    type: Array,
    default: () => [],
  },
  usageOptions: {
    type: Array,
    default: () => [],
  },
  titleDocumentOptions: {
    type: Array,
    default: () => [],
  },
  landShapeOptions: {
    type: Array,
    default: () => [],
  },
  landPositionOptions: {
    type: Array,
    default: () => [],
  },
  landConditionOptions: {
    type: Array,
    default: () => [],
  },
  topographyOptions: {
    type: Array,
    default: () => [],
  },
  provinces: {
    type: Array,
    default: () => [],
  },
  regencies: {
    type: Array,
    default: () => [],
  },
  districts: {
    type: Array,
    default: () => [],
  },
  villages: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  asset_code: props.record.asset_code ?? '',
  asset_type: props.record.asset_type ?? '',
  peruntukan: props.record.peruntukan ?? '',
  title_document: props.record.title_document ?? '',
  certificate_number: props.record.certificate_number ?? '',
  certificate_holder_name: props.record.certificate_holder_name ?? '',
  certificate_issued_at: props.record.certificate_issued_at ?? '',
  land_book_date: props.record.land_book_date ?? '',
  document_land_area: props.record.document_land_area ?? '',
  legal_notes: props.record.legal_notes ?? '',
  land_shape: props.record.land_shape ?? '',
  land_position: props.record.land_position ?? '',
  land_condition: props.record.land_condition ?? '',
  topography: props.record.topography ?? '',
  province_id: props.record.province_id ?? '',
  regency_id: props.record.regency_id ?? '',
  district_id: props.record.district_id ?? '',
  village_id: props.record.village_id ?? '',
  address: props.record.address ?? '',
  maps_link: props.record.maps_link ?? '',
  coordinates_lat: props.record.coordinates_lat ?? '',
  coordinates_lng: props.record.coordinates_lng ?? '',
  land_area: props.record.land_area ?? '',
  building_area: props.record.building_area ?? '',
  building_floors: props.record.building_floors ?? '',
  build_year: props.record.build_year ?? '',
  renovation_year: props.record.renovation_year ?? '',
  frontage_width: props.record.frontage_width ?? '',
  access_road_width: props.record.access_road_width ?? '',
});

const regencyOptions = ref([...(props.regencies ?? [])]);
const districtOptions = ref([...(props.districts ?? [])]);
const villageOptions = ref([...(props.villages ?? [])]);
const EMPTY_SELECT = '__empty__';

watch(() => props.regencies, (value) => {
  regencyOptions.value = [...(value ?? [])];
}, { deep: true });

watch(() => props.districts, (value) => {
  districtOptions.value = [...(value ?? [])];
}, { deep: true });

watch(() => props.villages, (value) => {
  villageOptions.value = [...(value ?? [])];
}, { deep: true });

const pageTitle = computed(() => (
  props.mode === 'edit'
    ? `Edit Aset - ${props.requestRecord.request_number}`
    : `Tambah Aset - ${props.requestRecord.request_number}`
));

const heading = computed(() => (
  props.mode === 'edit' ? 'Edit Aset' : 'Tambah Aset'
));

const submitLabel = computed(() => (
  props.mode === 'edit' ? 'Simpan Perubahan Aset' : 'Tambah Aset'
));


const isLandOnly = computed(() => form.asset_type === 'tanah');

watch(isLandOnly, (value) => {
  if (!value) {
    return;
  }

  form.building_area = '';
  form.building_floors = '';
  form.build_year = '';
  form.renovation_year = '';
});

const locationRouteName = computed(() => (
  props.mode === 'edit'
    ? 'admin.appraisal-requests.assets.edit'
    : 'admin.appraisal-requests.assets.create'
));

const toNullableSelectValue = (value) => (value ? value : EMPTY_SELECT);
const fromNullableSelectValue = (value) => (value === EMPTY_SELECT ? '' : value);

const locationRouteParams = computed(() => (
  props.mode === 'edit'
    ? { appraisalRequest: props.requestRecord.id, asset: props.record.id }
    : { appraisalRequest: props.requestRecord.id }
));

const reloadLocationOptions = (only, query) => {
  router.get(route(locationRouteName.value, {
    ...locationRouteParams.value,
    ...query,
  }), {}, {
    only,
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const onProvinceChange = (value) => {
  form.province_id = value || '';
  form.regency_id = '';
  form.district_id = '';
  form.village_id = '';
  regencyOptions.value = [];
  districtOptions.value = [];
  villageOptions.value = [];

  if (value) {
    reloadLocationOptions(['regencies', 'districts', 'villages'], {
      province_id: value,
    });
  }
};

const onRegencyChange = (value) => {
  form.regency_id = value || '';
  form.district_id = '';
  form.village_id = '';
  districtOptions.value = [];
  villageOptions.value = [];

  if (value) {
    reloadLocationOptions(['districts', 'villages'], {
      province_id: form.province_id || undefined,
      regency_id: value,
    });
  }
};

const onDistrictChange = (value) => {
  form.district_id = value || '';
  form.village_id = '';
  villageOptions.value = [];

  if (value) {
    reloadLocationOptions(['villages'], {
      province_id: form.province_id || undefined,
      regency_id: form.regency_id || undefined,
      district_id: value,
    });
  }
};

const submit = () => {
  if (props.mode === 'edit') {
    form.put(route('admin.appraisal-requests.assets.update', [props.requestRecord.id, props.record.id]), {
      preserveScroll: true,
    });

    return;
  }

  form.post(route('admin.appraisal-requests.assets.store', props.requestRecord.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="pageTitle" />

  <AdminLayout :title="heading">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Aset Request</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ heading }}</h1>
          <p class="mt-2 text-sm text-slate-600">
            Kelola data dasar aset dari workspace admin. Dokumen dan foto aset tetap dikelola melalui section terpisah.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child>
            <Link :href="requestRecord.show_url">Kembali ke detail</Link>
          </Button>


        </div>
      </section>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <CardHeader>
            <CardTitle>Informasi Objek</CardTitle>
            <CardDescription>Field inti aset untuk operasional admin dan proses valuasi.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <Label for="asset_code">Kode Objek</Label>
              <Input id="asset_code" v-model="form.asset_code" placeholder="Opsional" />
              <p v-if="form.errors.asset_code" class="text-xs text-red-500">{{ form.errors.asset_code }}</p>
            </div>

            <div class="space-y-2">
              <Label for="asset_type">Jenis Aset</Label>
              <Select v-model="form.asset_type">
                <SelectTrigger id="asset_type">
                  <SelectValue placeholder="Pilih jenis aset" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in assetTypeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.asset_type" class="text-xs text-red-500">{{ form.errors.asset_type }}</p>
            </div>

            <div class="space-y-2">
              <Label for="peruntukan">Peruntukan</Label>
              <Select
                :model-value="toNullableSelectValue(form.peruntukan)"
                @update:model-value="form.peruntukan = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="peruntukan">
                  <SelectValue placeholder="Pilih peruntukan" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in usageOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.peruntukan" class="text-xs text-red-500">{{ form.errors.peruntukan }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Karakteristik Tanah</CardTitle>
            <CardDescription>Semua field di sini tetap nullable agar perilaku admin sama dengan relation manager lama.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <Label for="title_document">Dokumen Tanah</Label>
              <Select
                :model-value="toNullableSelectValue(form.title_document)"
                @update:model-value="form.title_document = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="title_document">
                  <SelectValue placeholder="Pilih dokumen" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in titleDocumentOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.title_document" class="text-xs text-red-500">{{ form.errors.title_document }}</p>
            </div>

            <div class="space-y-2">
              <Label for="land_shape">Bentuk Tanah</Label>
              <Select
                :model-value="toNullableSelectValue(form.land_shape)"
                @update:model-value="form.land_shape = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="land_shape">
                  <SelectValue placeholder="Pilih bentuk tanah" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in landShapeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.land_shape" class="text-xs text-red-500">{{ form.errors.land_shape }}</p>
            </div>

            <div class="space-y-2">
              <Label for="land_position">Posisi Tanah</Label>
              <Select
                :model-value="toNullableSelectValue(form.land_position)"
                @update:model-value="form.land_position = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="land_position">
                  <SelectValue placeholder="Pilih posisi tanah" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in landPositionOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.land_position" class="text-xs text-red-500">{{ form.errors.land_position }}</p>
            </div>

            <div class="space-y-2">
              <Label for="land_condition">Kondisi Tanah</Label>
              <Select
                :model-value="toNullableSelectValue(form.land_condition)"
                @update:model-value="form.land_condition = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="land_condition">
                  <SelectValue placeholder="Pilih kondisi tanah" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in landConditionOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.land_condition" class="text-xs text-red-500">{{ form.errors.land_condition }}</p>
            </div>

            <div class="space-y-2">
              <Label for="topography">Topografi</Label>
              <Select
                :model-value="toNullableSelectValue(form.topography)"
                @update:model-value="form.topography = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="topography">
                  <SelectValue placeholder="Pilih topografi" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in topographyOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.topography" class="text-xs text-red-500">{{ form.errors.topography }}</p>
            </div>

            <div class="space-y-2">
              <Label for="frontage_width">Lebar Muka (m)</Label>
              <Input id="frontage_width" v-model="form.frontage_width" type="number" min="0" step="0.01" />
              <p v-if="form.errors.frontage_width" class="text-xs text-red-500">{{ form.errors.frontage_width }}</p>
            </div>

            <div class="space-y-2">
              <Label for="access_road_width">Lebar Akses Jalan (m)</Label>
              <Input id="access_road_width" v-model="form.access_road_width" type="number" min="0" step="0.01" />
              <p v-if="form.errors.access_road_width" class="text-xs text-red-500">{{ form.errors.access_road_width }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Metadata Legal untuk Report</CardTitle>
            <CardDescription>Field ini dipakai untuk menyusun bagian legalitas pada draft report DigiPro by KJPP HJAR.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <Label for="certificate_number">Nomor Sertifikat</Label>
              <Input id="certificate_number" v-model="form.certificate_number" placeholder="Contoh: SHM No. 685" />
              <p v-if="form.errors.certificate_number" class="text-xs text-red-500">{{ form.errors.certificate_number }}</p>
            </div>

            <div class="space-y-2">
              <Label for="certificate_holder_name">Nama Pemegang Hak</Label>
              <Input id="certificate_holder_name" v-model="form.certificate_holder_name" placeholder="Nama sesuai dokumen" />
              <p v-if="form.errors.certificate_holder_name" class="text-xs text-red-500">{{ form.errors.certificate_holder_name }}</p>
            </div>

            <div class="space-y-2">
              <Label for="document_land_area">Luas Menurut Dokumen (m2)</Label>
              <Input id="document_land_area" v-model="form.document_land_area" type="number" min="0" step="0.01" />
              <p v-if="form.errors.document_land_area" class="text-xs text-red-500">{{ form.errors.document_land_area }}</p>
            </div>

            <div class="space-y-2">
              <Label for="certificate_issued_at">Tanggal Terbit Sertifikat</Label>
              <Input id="certificate_issued_at" v-model="form.certificate_issued_at" type="date" />
              <p v-if="form.errors.certificate_issued_at" class="text-xs text-red-500">{{ form.errors.certificate_issued_at }}</p>
            </div>

            <div class="space-y-2">
              <Label for="land_book_date">Tanggal Buku Tanah</Label>
              <Input id="land_book_date" v-model="form.land_book_date" type="date" />
              <p v-if="form.errors.land_book_date" class="text-xs text-red-500">{{ form.errors.land_book_date }}</p>
            </div>

            <div class="space-y-2 md:col-span-2 xl:col-span-3">
              <Label for="legal_notes">Catatan Legal Tambahan</Label>
              <Textarea id="legal_notes" v-model="form.legal_notes" rows="4" placeholder="Opsional, misalnya catatan dokumen atau legalitas tambahan untuk kebutuhan report." />
              <p v-if="form.errors.legal_notes" class="text-xs text-red-500">{{ form.errors.legal_notes }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lokasi</CardTitle>
            <CardDescription>Partial reload hanya dipakai untuk option lokasi turunan agar form tetap ringan.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <Label for="province_id">Provinsi</Label>
              <Select :model-value="toNullableSelectValue(form.province_id)" @update:model-value="onProvinceChange(fromNullableSelectValue($event))">
                <SelectTrigger id="province_id">
                  <SelectValue placeholder="Pilih provinsi" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in provinces"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.province_id" class="text-xs text-red-500">{{ form.errors.province_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="regency_id">Kabupaten/Kota</Label>
              <Select
                :disabled="!form.province_id"
                :model-value="toNullableSelectValue(form.regency_id)"
                @update:model-value="onRegencyChange(fromNullableSelectValue($event))"
              >
                <SelectTrigger id="regency_id">
                  <SelectValue placeholder="Pilih kabupaten/kota" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in regencyOptions"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.regency_id" class="text-xs text-red-500">{{ form.errors.regency_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="district_id">Kecamatan</Label>
              <Select
                :disabled="!form.regency_id"
                :model-value="toNullableSelectValue(form.district_id)"
                @update:model-value="onDistrictChange(fromNullableSelectValue($event))"
              >
                <SelectTrigger id="district_id">
                  <SelectValue placeholder="Pilih kecamatan" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in districtOptions"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.district_id" class="text-xs text-red-500">{{ form.errors.district_id }}</p>
            </div>

            <div class="space-y-2">
              <Label for="village_id">Kelurahan/Desa</Label>
              <Select
                :model-value="toNullableSelectValue(form.village_id)"
                :disabled="!form.district_id"
                @update:model-value="form.village_id = fromNullableSelectValue($event)"
              >
                <SelectTrigger id="village_id">
                  <SelectValue placeholder="Pilih kelurahan/desa" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="EMPTY_SELECT">Tidak diisi</SelectItem>
                  <SelectItem
                    v-for="option in villageOptions"
                    :key="option.id"
                    :value="option.id"
                  >
                    {{ option.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.village_id" class="text-xs text-red-500">{{ form.errors.village_id }}</p>
            </div>

            <div class="space-y-2 md:col-span-2 xl:col-span-3">
              <Label for="address">Alamat Lengkap</Label>
              <Textarea id="address" v-model="form.address" rows="4" placeholder="Alamat aset" />
              <p v-if="form.errors.address" class="text-xs text-red-500">{{ form.errors.address }}</p>
            </div>

            <div class="space-y-2">
              <Label for="coordinates_lat">Latitude</Label>
              <Input id="coordinates_lat" v-model="form.coordinates_lat" type="number" step="0.0000001" />
              <p v-if="form.errors.coordinates_lat" class="text-xs text-red-500">{{ form.errors.coordinates_lat }}</p>
            </div>

            <div class="space-y-2">
              <Label for="coordinates_lng">Longitude</Label>
              <Input id="coordinates_lng" v-model="form.coordinates_lng" type="number" step="0.0000001" />
              <p v-if="form.errors.coordinates_lng" class="text-xs text-red-500">{{ form.errors.coordinates_lng }}</p>
            </div>

            <div class="space-y-2 md:col-span-2 xl:col-span-3">
              <Label for="maps_link">Link Google Maps</Label>
              <Input id="maps_link" v-model="form.maps_link" placeholder="https://maps.google.com/?q=..." />
              <p v-if="form.errors.maps_link" class="text-xs text-red-500">{{ form.errors.maps_link }}</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Luas & Bangunan</CardTitle>
            <CardDescription>Field bangunan tetap tersedia, tetapi tidak dipaksa ketika aset hanya berupa tanah.</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <div class="space-y-2">
              <Label for="land_area">Luas Tanah (m2)</Label>
              <Input id="land_area" v-model="form.land_area" type="number" min="0" step="0.01" />
              <p v-if="form.errors.land_area" class="text-xs text-red-500">{{ form.errors.land_area }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_area">Luas Bangunan (m2)</Label>
              <Input id="building_area" v-model="form.building_area" type="number" min="0" step="0.01" :disabled="isLandOnly" />
              <p v-if="form.errors.building_area" class="text-xs text-red-500">{{ form.errors.building_area }}</p>
            </div>

            <div class="space-y-2">
              <Label for="building_floors">Jumlah Lantai</Label>
              <Input id="building_floors" v-model="form.building_floors" type="number" min="0" :disabled="isLandOnly" />
              <p v-if="form.errors.building_floors" class="text-xs text-red-500">{{ form.errors.building_floors }}</p>
            </div>

            <div class="space-y-2">
              <Label for="build_year">Tahun Bangun</Label>
              <Input id="build_year" v-model="form.build_year" type="number" min="1900" :disabled="isLandOnly" />
              <p v-if="form.errors.build_year" class="text-xs text-red-500">{{ form.errors.build_year }}</p>
            </div>

            <div class="space-y-2">
              <Label for="renovation_year">Tahun Renovasi</Label>
              <Input id="renovation_year" v-model="form.renovation_year" type="number" min="1900" :disabled="isLandOnly" />
              <p v-if="form.errors.renovation_year" class="text-xs text-red-500">{{ form.errors.renovation_year }}</p>
            </div>
          </CardContent>
        </Card>

        <div class="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" as-child>
            <Link :href="requestRecord.show_url">Batal</Link>
          </Button>


          <Button type="submit" :disabled="form.processing">
            {{ submitLabel }}
          </Button>

        </div>
      </form>
    </div>
  </AdminLayout>
</template>

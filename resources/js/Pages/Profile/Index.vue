<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";
import ReviewerLayout from "@/layouts/ReviewerLayout.vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref, nextTick, onBeforeUnmount, watch } from "vue";
import { useNotification } from "@/composables/useNotification";

import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Loader2, Pencil, Upload, Trash2 } from "lucide-vue-next";

const page = usePage();
const { notify } = useNotification();

const user = computed(() => page.props.auth?.user || {});
const layoutContext = computed(() => page.props.layoutContext || "customer");
const isCustomer = computed(() => layoutContext.value === "customer");
const supportContact = computed(() => page.props.supportContact || null);
const profileLocationOptions = computed(() => page.props.profileLocationOptions || {});
const profileRoutes = computed(() => page.props.profileRoutes || {
  edit: "/profile",
  update: "/profile",
  password: "/profile/password",
  passwordVerify: "/profile/password/verify",
  avatar: route("profile.avatar"),
  avatarRemove: route("profile.avatar.remove"),
  locationOptions: route("profile.location-options"),
});
const layoutComponent = computed(() => {
  if (layoutContext.value === "reviewer") return ReviewerLayout;
  if (layoutContext.value === "admin") return AdminLayout;
  return UserDashboardLayout;
});
const defaultAvatarUrl = "/images/avatar-default.svg";
const avatarUrl = computed(() => user.value.avatar_url || defaultAvatarUrl);

const profileFormRef = ref(null);
const avatarInputRef = ref(null);
const cropCanvasRef = ref(null);
const cropDialogOpen = ref(false);
const cropPreviewUrl = ref("");
const selectedAvatarName = ref("");
const selectedImage = ref(null);
const cropScale = ref(1);
const cropPosition = ref({ x: 0, y: 0 });
const dragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const dragOrigin = ref({ x: 0, y: 0 });
const activeTab = ref("general");
const passwordDialogOpen = ref(false);
const passwordStep = ref(1);
const profileEditing = ref(false);
const twoFactorState = ref({
  enabled: Boolean(user.value.two_factor_enabled),
  confirmed: Boolean(user.value.two_factor_confirmed_at),
  loading: false,
  qrSvg: "",
  secretKey: "",
  recoveryCodes: [],
  showRecoveryCodes: false,
  code: "",
});

// form profil
const profileForm = useForm({
  name: user.value.name || "",
  email: user.value.email || "",
  phone_number: user.value.phone_number || "",
  whatsapp_number: user.value.whatsapp_number || "",
  address: user.value.address || "",
  billing_recipient_name: user.value.billing_recipient_name || "",
  billing_province_id: user.value.billing_province_id || "",
  billing_regency_id: user.value.billing_regency_id || "",
  billing_district_id: user.value.billing_district_id || "",
  billing_village_id: user.value.billing_village_id || "",
  billing_postal_code: user.value.billing_postal_code || "",
  billing_address_detail: user.value.billing_address_detail || "",
});

const provinceOptions = ref(profileLocationOptions.value.provinceOptions || []);
const regencyOptions = ref(profileLocationOptions.value.regencyOptions || []);
const districtOptions = ref(profileLocationOptions.value.districtOptions || []);
const villageOptions = ref(profileLocationOptions.value.villageOptions || []);

const avatarForm = useForm({
  avatar: null,
});

// form password
const passwordForm = useForm({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const submitProfile = () => {
  profileForm.put(profileRoutes.value.update, {
    preserveScroll: true,
    onSuccess: () => {
      notify("success", "Profil berhasil diperbarui.");
      profileEditing.value = false;
    },
    onError: () => {
      notify("error", "Gagal menyimpan profil, silakan periksa kembali form.");
    },
  });
};

const submitPassword = () => {
  passwordForm.put(profileRoutes.value.password, {
    preserveScroll: true,
    onSuccess: () => {
      notify("success", "Password berhasil diperbarui.");
      passwordForm.reset();
      passwordDialogOpen.value = false;
      passwordStep.value = 1;
    },
    onError: () => {
      notify("error", "Gagal mengubah password, periksa kembali isian.");
    },
  });
};

const focusProfileForm = () => {
  activeTab.value = "general";
  profileEditing.value = true;
  const target = document.getElementById("name");
  if (target) target.focus();
};

const cancelProfileEdit = () => {
  profileForm.name = user.value.name || "";
  profileForm.email = user.value.email || "";
  profileForm.phone_number = user.value.phone_number || "";
  profileForm.whatsapp_number = user.value.whatsapp_number || "";
  profileForm.address = user.value.address || "";
  profileForm.billing_recipient_name = user.value.billing_recipient_name || "";
  profileForm.billing_province_id = user.value.billing_province_id || "";
  profileForm.billing_regency_id = user.value.billing_regency_id || "";
  profileForm.billing_district_id = user.value.billing_district_id || "";
  profileForm.billing_village_id = user.value.billing_village_id || "";
  profileForm.billing_postal_code = user.value.billing_postal_code || "";
  profileForm.billing_address_detail = user.value.billing_address_detail || "";
  provinceOptions.value = profileLocationOptions.value.provinceOptions || [];
  regencyOptions.value = profileLocationOptions.value.regencyOptions || [];
  districtOptions.value = profileLocationOptions.value.districtOptions || [];
  villageOptions.value = profileLocationOptions.value.villageOptions || [];
  profileForm.clearErrors();
  profileEditing.value = false;
};

const selectedBillingLabels = computed(() => {
  const labels = profileLocationOptions.value.selectedLabels || {};
  const resolveLabel = (options, value, fallback = null) => {
    if (!value) return "-";
    return options.find((option) => option.value === value)?.label || fallback || value;
  };

  return {
    province: resolveLabel(provinceOptions.value, user.value.billing_province_id, labels.province),
    regency: resolveLabel(regencyOptions.value, user.value.billing_regency_id, labels.regency),
    district: resolveLabel(districtOptions.value, user.value.billing_district_id, labels.district),
    village: resolveLabel(villageOptions.value, user.value.billing_village_id, labels.village),
  };
});

const fetchProfileLocationOptions = async (type, params = {}) => {
  const url = profileRoutes.value.locationOptions;
  if (!url) return [];

  const response = await axios.get(url, {
    params: {
      type,
      ...params,
    },
  });

  return Array.isArray(response.data?.options) ? response.data.options : [];
};

const handleBillingProvinceChange = async (value) => {
  profileForm.billing_province_id = value;
  profileForm.billing_regency_id = "";
  profileForm.billing_district_id = "";
  profileForm.billing_village_id = "";
  regencyOptions.value = [];
  districtOptions.value = [];
  villageOptions.value = [];

  if (!value) return;

  regencyOptions.value = await fetchProfileLocationOptions("regencies", { province_id: value });
};

const handleBillingRegencyChange = async (value) => {
  profileForm.billing_regency_id = value;
  profileForm.billing_district_id = "";
  profileForm.billing_village_id = "";
  districtOptions.value = [];
  villageOptions.value = [];

  if (!value) return;

  districtOptions.value = await fetchProfileLocationOptions("districts", { regency_id: value });
};

const handleBillingDistrictChange = async (value) => {
  profileForm.billing_district_id = value;
  profileForm.billing_village_id = "";
  villageOptions.value = [];

  if (!value) return;

  villageOptions.value = await fetchProfileLocationOptions("villages", { district_id: value });
};

const openAvatarPicker = () => {
  avatarInputRef.value?.click();
};

const cleanupCropper = () => {
  dragging.value = false;
  selectedImage.value = null;
  cropScale.value = 1;
  cropPosition.value = { x: 0, y: 0 };
  if (cropPreviewUrl.value) {
    URL.revokeObjectURL(cropPreviewUrl.value);
    cropPreviewUrl.value = "";
  }
  selectedAvatarName.value = "";
};

const handleCropDialogOpen = (open) => {
  cropDialogOpen.value = open;
  if (!open) cleanupCropper();
};

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const getDrawRect = (size, positionOverride = null) => {
  const img = selectedImage.value;
  if (!img) return null;

  const baseScale = Math.max(size / img.width, size / img.height);
  const scale = baseScale * cropScale.value;
  const drawW = img.width * scale;
  const drawH = img.height * scale;

  let x = positionOverride?.x ?? cropPosition.value.x;
  let y = positionOverride?.y ?? cropPosition.value.y;

  if (!Number.isFinite(x) || !Number.isFinite(y)) {
    x = (size - drawW) / 2;
    y = (size - drawH) / 2;
  }

  const minX = size - drawW;
  const maxX = 0;
  const minY = size - drawH;
  const maxY = 0;

  x = clamp(x, minX, maxX);
  y = clamp(y, minY, maxY);

  if (!positionOverride) {
    cropPosition.value = { x, y };
  }

  return { x, y, drawW, drawH };
};

const drawCropPreview = () => {
  const canvas = cropCanvasRef.value;
  const img = selectedImage.value;
  if (!canvas || !img) return;

  const ctx = canvas.getContext("2d");
  if (!ctx) return;

  const size = canvas.width;
  ctx.clearRect(0, 0, size, size);

  const rect = getDrawRect(size);
  if (!rect) return;

  ctx.fillStyle = "#f1f5f9";
  ctx.fillRect(0, 0, size, size);
  ctx.drawImage(img, rect.x, rect.y, rect.drawW, rect.drawH);
};

const loadSelectedImage = (url) => {
  const img = new Image();
  img.onload = () => {
    selectedImage.value = img;
    cropScale.value = 1;

    const size = cropCanvasRef.value?.width || 320;
    const baseScale = Math.max(size / img.width, size / img.height);
    const drawW = img.width * baseScale;
    const drawH = img.height * baseScale;
    cropPosition.value = { x: (size - drawW) / 2, y: (size - drawH) / 2 };

    drawCropPreview();
  };
  img.src = url;
};

const onAvatarSelected = async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    notify("error", "File harus berupa gambar.");
    return;
  }

  selectedAvatarName.value = file.name;
  cropPreviewUrl.value = URL.createObjectURL(file);
  cropDialogOpen.value = true;

  await nextTick();
  loadSelectedImage(cropPreviewUrl.value);

  // reset input supaya file yg sama bisa dipilih lagi
  e.target.value = "";
};

const updateCropScale = (event) => {
  cropScale.value = Number(event.target.value) || 1;
  drawCropPreview();
};

const startDrag = (event) => {
  if (!selectedImage.value) return;
  dragging.value = true;
  dragStart.value = { x: event.clientX, y: event.clientY };
  dragOrigin.value = { ...cropPosition.value };
  event.currentTarget?.setPointerCapture?.(event.pointerId);
};

const onDrag = (event) => {
  if (!dragging.value) return;
  const dx = event.clientX - dragStart.value.x;
  const dy = event.clientY - dragStart.value.y;
  cropPosition.value = { x: dragOrigin.value.x + dx, y: dragOrigin.value.y + dy };
  drawCropPreview();
};

const endDrag = (event) => {
  dragging.value = false;
  event.currentTarget?.releasePointerCapture?.(event.pointerId);
};

const applyCrop = () => {
  const img = selectedImage.value;
  if (!img) return;

  const outputSize = 512;
  const previewSize = cropCanvasRef.value?.width || 320;
  const factor = outputSize / previewSize;

  const rect = getDrawRect(outputSize, {
    x: cropPosition.value.x * factor,
    y: cropPosition.value.y * factor,
  });

  if (!rect) {
    notify("error", "Gagal memproses gambar.");
    return;
  }

  const canvas = document.createElement("canvas");
  canvas.width = outputSize;
  canvas.height = outputSize;

  const ctx = canvas.getContext("2d");
  if (!ctx) return;

  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, outputSize, outputSize);
  ctx.drawImage(img, rect.x, rect.y, rect.drawW, rect.drawH);

  canvas.toBlob(
    (blob) => {
      if (!blob) {
        notify("error", "Gagal memproses gambar.");
        return;
      }

      const baseName = selectedAvatarName.value.replace(/\.[^/.]+$/, "");
      const file = new File([blob], `${baseName}.jpg`, { type: "image/jpeg" });
      avatarForm.avatar = file;

      avatarForm.post(profileRoutes.value.avatar, {
        preserveScroll: true,
        onSuccess: () => {
          notify("success", "Foto profil berhasil diperbarui.");
          handleCropDialogOpen(false);
        },
        onError: () => {
          notify("error", "Gagal mengunggah foto profil.");
        },
        onFinish: () => {
          avatarForm.reset("avatar");
        },
      });
    },
    "image/jpeg",
    0.9
  );
};

const removeAvatar = () => {
  router.delete(profileRoutes.value.avatarRemove, {
    preserveScroll: true,
    onSuccess: () => {
      notify("success", "Foto profil direset ke default.");
    },
    onError: () => {
      notify("error", "Gagal menghapus foto profil.");
    },
  });
};

const openPasswordDialog = () => {
  passwordStep.value = 1;
  passwordDialogOpen.value = true;
};

const handlePasswordDialogOpen = (open) => {
  if (open) {
    passwordDialogOpen.value = true;
    return;
  }
  closePasswordDialog();
};

const closePasswordDialog = () => {
  passwordDialogOpen.value = false;
  passwordStep.value = 1;
  passwordForm.reset();
  passwordForm.clearErrors();
};

const nextPasswordStep = () => {
  passwordStep.value = 2;
};

const backPasswordStep = () => {
  passwordStep.value = 1;
};

const verifyCurrentPassword = () => {
  passwordForm.post(profileRoutes.value.passwordVerify, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      passwordForm.clearErrors("current_password");
      nextPasswordStep();
    },
    onError: () => {
      notify("error", "Password lama tidak sesuai.");
    },
  });
};

const fetchTwoFactorSetup = async () => {
  if (!twoFactorState.value.enabled) return;
  try {
    const [qrRes, secretRes] = await Promise.all([
      axios.get(route("two-factor.qr-code")),
      axios.get(route("two-factor.secret-key")),
    ]);
    twoFactorState.value.qrSvg = qrRes.data?.svg || "";
    twoFactorState.value.secretKey = secretRes.data?.secretKey || "";
  } catch (_) {
    notify("error", "Gagal memuat QR code 2FA.");
  }
};

const fetchRecoveryCodes = async () => {
  try {
    const res = await axios.get(route("two-factor.recovery-codes"));
    twoFactorState.value.recoveryCodes = Array.isArray(res.data) ? res.data : [];
  } catch (_) {
    notify("error", "Gagal memuat recovery code.");
  }
};

const enableTwoFactor = async () => {
  twoFactorState.value.loading = true;
  try {
    await axios.post(route("two-factor.enable"));
    twoFactorState.value.enabled = true;
    await fetchTwoFactorSetup();
    await fetchRecoveryCodes();
    notify("success", "2FA diaktifkan. Silakan konfirmasi dengan kode.");
  } catch (_) {
    notify("error", "Gagal mengaktifkan 2FA.");
  } finally {
    twoFactorState.value.loading = false;
  }
};

const confirmTwoFactor = async () => {
  if (!twoFactorState.value.code) return;
  twoFactorState.value.loading = true;
  try {
    await axios.post(route("two-factor.confirm"), {
      code: twoFactorState.value.code,
    });
    twoFactorState.value.confirmed = true;
    twoFactorState.value.code = "";
    await fetchRecoveryCodes();
    notify("success", "2FA berhasil dikonfirmasi.");
  } catch (_) {
    notify("error", "Kode 2FA tidak valid.");
  } finally {
    twoFactorState.value.loading = false;
  }
};

const disableTwoFactor = async () => {
  twoFactorState.value.loading = true;
  try {
    await axios.delete(route("two-factor.disable"));
    twoFactorState.value = {
      enabled: false,
      confirmed: false,
      loading: false,
      qrSvg: "",
      secretKey: "",
      recoveryCodes: [],
      showRecoveryCodes: false,
      code: "",
    };
    notify("success", "2FA dinonaktifkan.");
  } catch (_) {
    notify("error", "Gagal menonaktifkan 2FA.");
  } finally {
    twoFactorState.value.loading = false;
  }
};

const regenerateRecoveryCodes = async () => {
  twoFactorState.value.loading = true;
  try {
    await axios.post(route("two-factor.regenerate-recovery-codes"));
    await fetchRecoveryCodes();
    notify("success", "Recovery code diperbarui.");
  } catch (_) {
    notify("error", "Gagal memperbarui recovery code.");
  } finally {
    twoFactorState.value.loading = false;
  }
};

onBeforeUnmount(() => {
  cleanupCropper();
});

watch(activeTab, (val) => {
  if (val === "security" && twoFactorState.value.enabled && !twoFactorState.value.qrSvg) {
    fetchTwoFactorSetup();
  }
  if (val === "security" && twoFactorState.value.enabled && twoFactorState.value.confirmed && !twoFactorState.value.recoveryCodes.length) {
    fetchRecoveryCodes();
  }
});
</script>

<template>
  <component :is="layoutComponent" title="Profil Saya">
    <template v-if="layoutContext !== 'reviewer'" #title>Profil Saya</template>

    <div class="max-w-4xl space-y-6">
      <Card class="shadow-sm">
        <CardHeader>
          <CardTitle class="text-base font-semibold text-slate-900">Pengaturan Profil</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
              <TabsTrigger value="general">Informasi Umum</TabsTrigger>
              <TabsTrigger value="security">Keamanan</TabsTrigger>
            </TabsList>

            <TabsContent value="general" class="space-y-6">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                  <Avatar class="h-16 w-16 border border-slate-200">
                    <AvatarImage :src="avatarUrl" />
                    <AvatarFallback class="text-sm font-semibold text-slate-700">
                      {{ (user.name || "U").slice(0, 1).toUpperCase() }}
                    </AvatarFallback>
                  </Avatar>
                  <div class="space-y-1">
                    <div class="text-lg font-semibold text-slate-900">{{ user.name || "-" }}</div>
                    <div class="text-sm text-slate-500">{{ user.email || "-" }}</div>
                    <div
                      v-if="isCustomer && !user.phone_number"
                      class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800"
                    >
                      Nomor telepon belum diatur. Mohon lengkapi profil.
                    </div>
                  </div>
                </div>

                <Button variant="outline" size="sm" class="gap-2" @click="focusProfileForm">
                  <Pencil class="h-4 w-4" />
                  Edit Profil
                </Button>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <input
                  ref="avatarInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onAvatarSelected"
                />
                <Button type="button" variant="secondary" size="sm" class="gap-2" @click="openAvatarPicker">
                  <Upload class="h-4 w-4" />
                  Upload Foto
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="gap-2 text-slate-600"
                  :disabled="!user.avatar_url"
                  @click="removeAvatar"
                >
                  <Trash2 class="h-4 w-4" />
                  Reset ke Default
                </Button>
                <span class="text-xs text-slate-500">JPG/PNG, maks 2 MB</span>
              </div>

              <Separator />

              <div ref="profileFormRef">
                <form @submit.prevent="submitProfile" class="space-y-6">
                  <section class="space-y-4">
                    <div>
                      <h3 class="text-sm font-semibold text-slate-900">Akun Dasar</h3>
                      <p class="text-xs text-slate-500">Informasi identitas utama yang tampil di akun Anda.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label for="name">Nama Lengkap</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.name || "-" }}
                        </div>
                        <div v-else>
                          <Input id="name" v-model="profileForm.name" autocomplete="name" />
                          <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.name }}
                          </p>
                        </div>
                      </div>

                      <div class="space-y-2">
                        <Label for="email">Email</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.email || "-" }}
                        </div>
                        <div v-else>
                          <Input
                            id="email"
                            type="email"
                            v-model="profileForm.email"
                            autocomplete="email"
                          />
                          <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.email }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </section>

                  <section class="space-y-4">
                    <div>
                      <h3 class="text-sm font-semibold text-slate-900">Kontak Utama</h3>
                      <p class="text-xs text-slate-500">Nomor telepon dipakai untuk follow-up operasional seperti review pembatalan dan klarifikasi admin.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label for="phone_number">Nomor Telepon</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.phone_number || "-" }}
                        </div>
                        <div v-else>
                          <Input id="phone_number" v-model="profileForm.phone_number" autocomplete="tel" />
                          <p v-if="profileForm.errors.phone_number" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.phone_number }}
                          </p>
                        </div>
                      </div>

                      <div class="space-y-2">
                        <Label for="whatsapp_number">Nomor WhatsApp</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.whatsapp_number || "-" }}
                        </div>
                        <div v-else>
                          <Input id="whatsapp_number" v-model="profileForm.whatsapp_number" autocomplete="tel" />
                          <p v-if="profileForm.errors.whatsapp_number" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.whatsapp_number }}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <Label for="address">Alamat</Label>
                      <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                        {{ user.address || "-" }}
                      </div>
                      <div v-else>
                        <Textarea id="address" v-model="profileForm.address" rows="4" />
                        <p v-if="profileForm.errors.address" class="mt-1 text-xs text-red-500">
                          {{ profileForm.errors.address }}
                        </p>
                      </div>
                    </div>
                  </section>

                  <section v-if="isCustomer" class="space-y-4">
                    <div>
                      <h3 class="text-sm font-semibold text-slate-900">Billing Ringkas</h3>
                      <p class="text-xs text-slate-500">Alamat ini digunakan untuk pengiriman laporan fisik ke alamat yang dicantumkan.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label for="billing_recipient_name">Nama Billing / Penerima Laporan</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.billing_recipient_name || "-" }}
                        </div>
                        <div v-else>
                          <Input id="billing_recipient_name" v-model="profileForm.billing_recipient_name" />
                          <p v-if="profileForm.errors.billing_recipient_name" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_recipient_name }}
                          </p>
                        </div>
                      </div>

                      <div class="space-y-2">
                        <Label for="billing_postal_code">Kode Pos</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ user.billing_postal_code || "-" }}
                        </div>
                        <div v-else>
                          <Input id="billing_postal_code" v-model="profileForm.billing_postal_code" inputmode="numeric" />
                          <p v-if="profileForm.errors.billing_postal_code" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_postal_code }}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label for="billing_province_id">Provinsi</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ selectedBillingLabels.province }}
                        </div>
                        <div v-else>
                          <Select
                            :model-value="profileForm.billing_province_id || undefined"
                            @update:model-value="handleBillingProvinceChange"
                          >
                            <SelectTrigger id="billing_province_id" class="w-full">
                              <SelectValue placeholder="Pilih provinsi" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem v-for="option in provinceOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                              </SelectItem>
                            </SelectContent>
                          </Select>
                          <p v-if="profileForm.errors.billing_province_id" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_province_id }}
                          </p>
                        </div>
                      </div>

                      <div class="space-y-2">
                        <Label for="billing_regency_id">Kabupaten / Kota</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ selectedBillingLabels.regency }}
                        </div>
                        <div v-else>
                          <Select
                            :model-value="profileForm.billing_regency_id || undefined"
                            @update:model-value="handleBillingRegencyChange"
                          >
                            <SelectTrigger id="billing_regency_id" class="w-full">
                              <SelectValue placeholder="Pilih kabupaten / kota" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem v-for="option in regencyOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                              </SelectItem>
                            </SelectContent>
                          </Select>
                          <p v-if="profileForm.errors.billing_regency_id" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_regency_id }}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div class="space-y-2">
                        <Label for="billing_district_id">Kecamatan</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ selectedBillingLabels.district }}
                        </div>
                        <div v-else>
                          <Select
                            :model-value="profileForm.billing_district_id || undefined"
                            @update:model-value="handleBillingDistrictChange"
                          >
                            <SelectTrigger id="billing_district_id" class="w-full">
                              <SelectValue placeholder="Pilih kecamatan" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem v-for="option in districtOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                              </SelectItem>
                            </SelectContent>
                          </Select>
                          <p v-if="profileForm.errors.billing_district_id" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_district_id }}
                          </p>
                        </div>
                      </div>

                      <div class="space-y-2">
                        <Label for="billing_village_id">Kelurahan / Desa</Label>
                        <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                          {{ selectedBillingLabels.village }}
                        </div>
                        <div v-else>
                          <Select
                            v-model="profileForm.billing_village_id"
                          >
                            <SelectTrigger id="billing_village_id" class="w-full">
                              <SelectValue placeholder="Pilih kelurahan / desa" />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem v-for="option in villageOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                              </SelectItem>
                            </SelectContent>
                          </Select>
                          <p v-if="profileForm.errors.billing_village_id" class="mt-1 text-xs text-red-500">
                            {{ profileForm.errors.billing_village_id }}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div class="space-y-2">
                      <Label for="billing_address_detail">Alamat Detail Billing</Label>
                      <p class="text-xs text-slate-500">
                        Alamat ini digunakan untuk pengiriman laporan fisik ke alamat yang dicantumkan.
                      </p>
                      <div v-if="!profileEditing" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-900">
                        {{ user.billing_address_detail || "-" }}
                      </div>
                      <div v-else>
                        <Textarea id="billing_address_detail" v-model="profileForm.billing_address_detail" rows="4" />
                        <p v-if="profileForm.errors.billing_address_detail" class="mt-1 text-xs text-red-500">
                          {{ profileForm.errors.billing_address_detail }}
                        </p>
                      </div>
                    </div>
                  </section>

                  <section v-if="isCustomer && supportContact" class="space-y-4">
                    <div>
                      <h3 class="text-sm font-semibold text-slate-900">Butuh Bantuan</h3>
                      <p class="text-xs text-slate-500">Gunakan kontak resmi ini bila Anda membutuhkan klarifikasi langsung dari tim admin.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 md:grid-cols-2">
                      <div class="space-y-1">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Contact Person</div>
                        <div class="text-sm font-medium text-slate-950">{{ supportContact.name || "-" }}</div>
                      </div>
                      <div class="space-y-1">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Nomor Telepon</div>
                        <div class="text-sm font-medium text-slate-950">{{ supportContact.phone || "-" }}</div>
                      </div>
                      <div class="space-y-1">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">WhatsApp</div>
                        <div class="text-sm font-medium text-slate-950">{{ supportContact.whatsapp || "-" }}</div>
                      </div>
                      <div class="space-y-1">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Email</div>
                        <div class="text-sm font-medium text-slate-950">{{ supportContact.email || "-" }}</div>
                      </div>
                      <div class="space-y-1 md:col-span-2">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-500">Jam Layanan</div>
                        <div class="text-sm font-medium text-slate-950">{{ supportContact.availability_label || "-" }}</div>
                      </div>
                    </div>
                  </section>

                  <div v-if="profileEditing" class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="cancelProfileEdit">
                      Batal
                    </Button>
                    <Button
                      type="submit"
                      class="bg-slate-900 hover:bg-slate-800"
                      :disabled="profileForm.processing"
                    >
                      <Loader2
                        v-if="profileForm.processing"
                        class="mr-2 h-4 w-4 animate-spin"
                      />
                      Simpan Perubahan
                    </Button>
                  </div>
                </form>
              </div>
            </TabsContent>

            <TabsContent value="security" class="space-y-6">
              <div class="rounded-xl border p-4 space-y-3">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="text-sm font-semibold text-slate-900">Ubah Password</div>
                    <p class="text-xs text-slate-500">
                      Perbarui password Anda untuk menjaga keamanan akun.
                    </p>
                  </div>
                  <Button variant="secondary" size="sm" @click="openPasswordDialog">
                    Ubah
                  </Button>
                </div>
              </div>

              <div class="rounded-xl border p-4 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div class="text-sm font-semibold text-slate-900">Two-Factor Authentication</div>
                    <p class="text-xs text-slate-500">
                      Gunakan aplikasi seperti Google Authenticator atau Authy.
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span
                      class="text-[11px] px-2 py-1 rounded-full"
                      :class="twoFactorState.enabled && twoFactorState.confirmed
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-slate-100 text-slate-600'"
                    >
                      {{ twoFactorState.enabled && twoFactorState.confirmed ? 'Aktif' : 'Belum Aktif' }}
                    </span>
                    <Button
                      v-if="!twoFactorState.enabled"
                      variant="secondary"
                      size="sm"
                      :disabled="twoFactorState.loading"
                      @click="enableTwoFactor"
                    >
                      <Loader2 v-if="twoFactorState.loading" class="mr-2 h-4 w-4 animate-spin" />
                      Aktifkan
                    </Button>
                    <Button
                      v-else
                      variant="outline"
                      size="sm"
                      :disabled="twoFactorState.loading"
                      @click="disableTwoFactor"
                    >
                      Nonaktifkan
                    </Button>
                  </div>
                </div>

                <div v-if="twoFactorState.enabled" class="space-y-4">
                  <div v-if="!twoFactorState.confirmed" class="rounded-lg border p-4 bg-slate-50">
                    <div class="text-xs text-slate-500 mb-3">
                      Scan QR code, lalu masukkan kode 6 digit untuk konfirmasi.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="rounded-md border bg-white p-3 flex items-center justify-center min-h-40">
                        <div v-if="twoFactorState.qrSvg" v-html="twoFactorState.qrSvg"></div>
                        <div v-else class="text-xs text-slate-500">Memuat QR...</div>
                      </div>
                      <div class="space-y-3">
                        <div>
                          <div class="text-xs text-slate-500">Kode Manual</div>
                          <div class="font-mono text-sm text-slate-900 break-all">
                            {{ twoFactorState.secretKey || '-' }}
                          </div>
                        </div>
                        <div class="space-y-2">
                          <Label for="two_factor_code">Kode 6 Digit</Label>
                          <Input
                            id="two_factor_code"
                            inputmode="numeric"
                            maxlength="6"
                            v-model="twoFactorState.code"
                            placeholder="123456"
                          />
                        </div>
                        <Button
                          type="button"
                          class="bg-slate-900 hover:bg-slate-800"
                          :disabled="twoFactorState.loading || !twoFactorState.code"
                          @click="confirmTwoFactor"
                        >
                          <Loader2 v-if="twoFactorState.loading" class="mr-2 h-4 w-4 animate-spin" />
                          Konfirmasi
                        </Button>
                      </div>
                    </div>
                  </div>

                  <div v-else class="rounded-lg border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <div class="text-sm font-semibold text-slate-900">Recovery Codes</div>
                        <p class="text-xs text-slate-500">
                          Simpan kode ini di tempat aman.
                        </p>
                      </div>
                      <div class="flex items-center gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          @click="twoFactorState.showRecoveryCodes = !twoFactorState.showRecoveryCodes"
                        >
                          {{ twoFactorState.showRecoveryCodes ? 'Sembunyikan' : 'Lihat' }}
                        </Button>
                        <Button
                          variant="secondary"
                          size="sm"
                          :disabled="twoFactorState.loading"
                          @click="regenerateRecoveryCodes"
                        >
                          Perbarui
                        </Button>
                      </div>
                    </div>

                    <div
                      v-if="twoFactorState.showRecoveryCodes"
                      class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2"
                    >
                      <div
                        v-for="code in twoFactorState.recoveryCodes"
                        :key="code"
                        class="rounded-md border bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
                      >
                        {{ code }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>

    <!-- Avatar Crop Dialog -->
    <Dialog :open="cropDialogOpen" @update:open="handleCropDialogOpen">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Atur Foto Profil</DialogTitle>
          <DialogDescription>
            Geser dan zoom agar foto pas di frame.
          </DialogDescription>
        </DialogHeader>

        <div class="mt-2">
          <div class="w-full rounded-lg border bg-slate-50 p-2">
            <div class="h-72 w-full overflow-hidden rounded-md bg-slate-100 flex items-center justify-center">
              <canvas
                v-if="cropPreviewUrl"
                ref="cropCanvasRef"
                width="320"
                height="320"
                class="h-full w-full cursor-move"
                @pointerdown="startDrag"
                @pointermove="onDrag"
                @pointerup="endDrag"
                @pointerleave="endDrag"
              ></canvas>
            </div>
            <div class="mt-3 flex items-center gap-3">
              <span class="text-xs text-slate-500">Zoom</span>
              <input
                type="range"
                min="1"
                max="2.5"
                step="0.01"
                :value="cropScale"
                @input="updateCropScale"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button variant="outline" type="button" @click="handleCropDialogOpen(false)">
            Batal
          </Button>
          <Button
            type="button"
            class="bg-slate-900 hover:bg-slate-800"
            :disabled="avatarForm.processing"
            @click="applyCrop"
          >
            <Loader2 v-if="avatarForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Simpan Foto
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Password Step Dialog -->
    <Dialog :open="passwordDialogOpen" @update:open="handlePasswordDialogOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Ubah Password</DialogTitle>
          <DialogDescription>
            {{ passwordStep === 1 ? "Masukkan password lama terlebih dahulu." : "Buat password baru Anda." }}
          </DialogDescription>
        </DialogHeader>

        <div v-if="passwordStep === 1" class="space-y-4">
          <div class="space-y-2">
            <Label for="current_password_modal">Password Lama</Label>
            <Input
              id="current_password_modal"
              type="password"
              v-model="passwordForm.current_password"
              autocomplete="current-password"
            />
            <p v-if="passwordForm.errors.current_password" class="text-xs text-red-500 mt-1">
              {{ passwordForm.errors.current_password }}
            </p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-1 gap-4">
            <div class="space-y-2">
              <Label for="password_modal">Password Baru</Label>
              <Input
                id="password_modal"
                type="password"
                v-model="passwordForm.password"
                autocomplete="new-password"
              />
              <p v-if="passwordForm.errors.password" class="text-xs text-red-500 mt-1">
                {{ passwordForm.errors.password }}
              </p>
            </div>

            <div class="space-y-2">
              <Label for="password_confirmation_modal">Konfirmasi Password Baru</Label>
              <Input
                id="password_confirmation_modal"
                type="password"
                v-model="passwordForm.password_confirmation"
                autocomplete="new-password"
              />
            </div>
          </div>
        </div>

        <DialogFooter class="gap-2 sm:justify-end">
          <Button variant="outline" type="button" @click="passwordStep === 1 ? closePasswordDialog() : backPasswordStep()">
            {{ passwordStep === 1 ? "Batal" : "Kembali" }}
          </Button>
          <Button
            v-if="passwordStep === 1"
            type="button"
            class="bg-slate-900 hover:bg-slate-800"
            :disabled="!passwordForm.current_password || passwordForm.processing"
            @click="verifyCurrentPassword"
          >
            <Loader2 v-if="passwordForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Lanjut
          </Button>
          <Button
            v-else
            type="button"
            class="bg-slate-900 hover:bg-slate-800"
            :disabled="passwordForm.processing"
            @click="submitPassword"
          >
            <Loader2 v-if="passwordForm.processing" class="mr-2 h-4 w-4 animate-spin" />
            Simpan Password
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </component>
</template>

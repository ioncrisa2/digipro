<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";
import ReviewerLayout from "@/layouts/ReviewerLayout.vue";
import ProfileAvatarCropDialog from "@/Pages/Profile/components/ProfileAvatarCropDialog.vue";
import ProfileGeneralTab from "@/Pages/Profile/components/ProfileGeneralTab.vue";
import ProfilePasswordDialog from "@/Pages/Profile/components/ProfilePasswordDialog.vue";
import ProfileSecurityTab from "@/Pages/Profile/components/ProfileSecurityTab.vue";
import { useForm, usePage, router } from "@inertiajs/vue3";
import axios from "axios";
import { computed, ref, nextTick, onBeforeUnmount, watch } from "vue";
import { useNotification } from "@/composables/useNotification";

import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

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
  billing_npwp: user.value.billing_npwp || "",
  billing_nik: user.value.billing_nik || "",
  billing_email: user.value.billing_email || "",
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
  profileForm.billing_npwp = user.value.billing_npwp || "";
  profileForm.billing_nik = user.value.billing_nik || "";
  profileForm.billing_email = user.value.billing_email || "";
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

const billingDeliveryReady = computed(() => {
  return Boolean(
    user.value.phone_number &&
    user.value.billing_recipient_name &&
    user.value.billing_address_detail
  );
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

const toggleRecoveryCodes = () => {
  twoFactorState.value.showRecoveryCodes = !twoFactorState.value.showRecoveryCodes;
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
              <div ref="profileFormRef">
                <ProfileGeneralTab
                  :user="user"
                  :is-customer="isCustomer"
                  :support-contact="supportContact"
                  :avatar-url="avatarUrl"
                  :avatar-input-ref="avatarInputRef"
                  :profile-editing="profileEditing"
                  :profile-form="profileForm"
                  :selected-billing-labels="selectedBillingLabels"
                  :billing-delivery-ready="billingDeliveryReady"
                  :province-options="provinceOptions"
                  :regency-options="regencyOptions"
                  :district-options="districtOptions"
                  :village-options="villageOptions"
                  @edit-profile="focusProfileForm"
                  @open-avatar-picker="openAvatarPicker"
                  @avatar-selected="onAvatarSelected"
                  @remove-avatar="removeAvatar"
                  @submit-profile="submitProfile"
                  @cancel-edit="cancelProfileEdit"
                  @billing-province-change="handleBillingProvinceChange"
                  @billing-regency-change="handleBillingRegencyChange"
                  @billing-district-change="handleBillingDistrictChange"
                />
              </div>
            </TabsContent>

            <TabsContent value="security" class="space-y-6">
              <ProfileSecurityTab
                :two-factor-state="twoFactorState"
                @open-password-dialog="openPasswordDialog"
                @enable-two-factor="enableTwoFactor"
                @disable-two-factor="disableTwoFactor"
                @confirm-two-factor="confirmTwoFactor"
                @toggle-recovery-codes="toggleRecoveryCodes"
                @regenerate-recovery-codes="regenerateRecoveryCodes"
              />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>

    <ProfileAvatarCropDialog
      :open="cropDialogOpen"
      :avatar-form="avatarForm"
      :crop-preview-url="cropPreviewUrl"
      :crop-scale="cropScale"
      :crop-canvas-ref="cropCanvasRef"
      @update:open="handleCropDialogOpen"
      @start-drag="startDrag"
      @drag="onDrag"
      @end-drag="endDrag"
      @update-crop-scale="updateCropScale"
      @apply-crop="applyCrop"
    />

    <ProfilePasswordDialog
      :open="passwordDialogOpen"
      :step="passwordStep"
      :password-form="passwordForm"
      @update:open="handlePasswordDialogOpen"
      @close="closePasswordDialog"
      @back="backPasswordStep"
      @verify="verifyCurrentPassword"
      @submit="submitPassword"
    />
  </component>
</template>

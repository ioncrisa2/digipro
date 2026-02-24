<script setup>
import { ref, onMounted } from "vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

const props = defineProps({
  consentData: {
    type: Object,
    required: true,
  },
  consentSubmitting: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["accept", "decline"]);

const scrollerRef = ref(null);
const hasReachedBottom = ref(false);
const consentAgreed = ref(false);

function normalizeConsentItem(item) {
  if (typeof item === "string") return item;
  if (item && typeof item === "object") {
    if (typeof item.text === "string") return item.text;
    try {
      return JSON.stringify(item);
    } catch (e) {
      return String(item);
    }
  }
  return "";
}

function onConsentScroll() {
  const el = scrollerRef.value;
  if (!el) return;

  const threshold = 24;
  const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - threshold;

  hasReachedBottom.value = atBottom;
  if (!atBottom) consentAgreed.value = false;
}

function handleDecline() {
  emit("decline");
}

function handleAccept() {
  if (!consentAgreed.value) return;
  emit("accept");
}

onMounted(() => {
  onConsentScroll();
});
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle>{{ consentData.title || "Persetujuan & Disclaimer" }}</CardTitle>
      <CardDescription>
        Anda harus menyetujui persetujuan & disclaimer berikut sebelum membuat permohonan penilaian baru.
      </CardDescription>
    </CardHeader>
    <CardContent>
      <div
        ref="scrollerRef"
        class="border rounded-lg p-4 h-[420px] overflow-y-auto bg-background"
        @scroll="onConsentScroll"
      >
        <div class="space-y-6">
          <section
            v-for="(s, idx) in consentData.sections"
            :key="idx"
            class="space-y-3"
          >
            <h2 class="text-lg font-semibold">{{ s.heading }}</h2>
            <p v-if="s.lead" class="text-sm text-muted-foreground">{{ s.lead }}</p>

            <ul v-if="s.items?.length" class="list-disc pl-5 space-y-2 text-sm">
              <li v-for="(item, i) in s.items" :key="i">
                {{ normalizeConsentItem(item) }}
              </li>
            </ul>
          </section>

          <div class="text-xs text-muted-foreground pt-4 border-t">
            Versi: {{ consentData.version }}
            <span v-if="consentData.hash" class="ml-2">
              - Hash: {{ String(consentData.hash).slice(0, 12) }}...
            </span>
          </div>
        </div>
      </div>

      <div class="mt-4">
        <label class="flex items-start gap-3">
          <input
            type="checkbox"
            class="mt-1"
            :disabled="!hasReachedBottom"
            v-model="consentAgreed"
          />
          <div>
            <div class="text-sm">
              {{
                consentData.checkbox_label ||
                "Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas."
              }}
            </div>
            <div v-if="!hasReachedBottom" class="text-xs text-muted-foreground">
              Scroll sampai bagian paling bawah agar checkbox dapat dicentang.
            </div>
          </div>
        </label>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <Button
          variant="outline"
          :disabled="consentSubmitting"
          @click="handleDecline"
        >
          Tidak Setuju
        </Button>

        <Button
          :disabled="consentSubmitting || !consentAgreed"
          @click="handleAccept"
        >
          Setuju & Lanjutkan
        </Button>
      </div>
    </CardContent>
  </Card>
</template>

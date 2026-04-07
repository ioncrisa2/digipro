<script setup>
import { Card, CardContent } from "@/components/ui/card";
import { AlertCircle } from "lucide-vue-next";
</script>

<template>
  <Card class="rounded-[2rem] border-dashed border-slate-300 bg-slate-50 shadow-none">
    <CardContent class="p-6">
      <div class="flex items-start gap-3">
        <div class="rounded-xl bg-white p-2">
          <AlertCircle class="w-5 h-5 text-slate-600" />
        </div>
        <div class="flex-1">
          <h4 class="text-sm font-semibold text-slate-900 mb-1">
            Informasi Penting
          </h4>
          <p class="text-xs text-slate-600 leading-relaxed">
            Untuk perubahan data profil dan dokumen pendukung dapat dilakukan melalui menu
            <span class="font-semibold text-slate-900">Profil Saya</span>.
          </p>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

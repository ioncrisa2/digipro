<script setup>
import { Card, CardContent } from "@/components/ui/card";
import { AlertCircle } from "lucide-vue-next";
</script>

<template>
  <Card class="shadow-sm bg-slate-50 border-slate-200">
    <CardContent class="p-6">
      <div class="flex items-start gap-3">
        <div class="bg-slate-200 p-2 rounded-lg">
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

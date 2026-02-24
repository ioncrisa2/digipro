<script setup>
import { Link } from "@inertiajs/vue3";
import UserDashboardLayout from "@/layouts/UserDashboardLayout.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Separator } from "@/components/ui/separator";
import { FileDown, Calendar, CreditCard, ArrowLeft } from "lucide-vue-next";

const props = defineProps({
  payment: { type: Object, required: true },
});

const statusTone = (status) => {
  const s = String(status || "").toLowerCase();
  if (s.includes("dibayar")) return "default";
  if (s.includes("menunggu")) return "secondary";
  return "outline";
};
</script>

<template>
  <UserDashboardLayout>
    <template #title>Detail Pembayaran</template>

    <div class="max-w-5xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <Link :href="route('payments.index')" class="text-slate-600 hover:text-slate-900">
            <ArrowLeft class="h-5 w-5" />
          </Link>
          <div>
            <h1 class="text-2xl font-semibold text-slate-900">Detail Pembayaran</h1>
            <p class="text-sm text-slate-500">
              {{ payment.invoice_number }} • {{ payment.client }}
            </p>
          </div>
        </div>
        <Badge :variant="statusTone(payment.status)">{{ payment.status }}</Badge>
      </div>

      <Card class="shadow-sm">
        <CardHeader>
          <CardTitle class="text-base">Informasi Pembayaran</CardTitle>
          <CardDescription>Ringkasan detail pembayaran</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3 text-sm">
            <div class="flex items-center gap-2 text-slate-700">
              <CreditCard class="h-4 w-4 text-slate-400" />
              <span class="font-semibold text-slate-900">{{ payment.amount }}</span>
            </div>
            <div class="flex items-center gap-2 text-slate-600">
              <Calendar class="h-4 w-4 text-slate-400" />
              <span>Jatuh tempo: {{ payment.due_date }}</span>
            </div>
            <div class="text-slate-600">
              Metode: <span class="font-medium text-slate-900">{{ payment.method }}</span>
            </div>
          </div>

          <div class="rounded-lg border p-4 text-sm">
            <div class="text-xs text-slate-500">Bank</div>
            <div class="font-medium text-slate-900">{{ payment.bank }}</div>
            <div class="text-xs text-slate-500 mt-2">Virtual Account / Rekening</div>
            <div class="font-mono text-slate-900">{{ payment.va }}</div>
          </div>

          <Separator />

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div
              v-for="doc in payment.documents"
              :key="doc.type"
              class="rounded-xl border border-l-4 border-l-slate-900 p-4 space-y-2"
            >
              <div class="text-xs text-slate-500 uppercase tracking-wide">{{ doc.label }}</div>
              <div class="font-medium text-slate-900">{{ doc.name }}</div>
              <div class="text-xs text-slate-500">{{ doc.size }}</div>
              <Button variant="outline" size="sm" class="mt-2 w-full" disabled>
                <FileDown class="mr-2 h-4 w-4" />
                Unduh
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </UserDashboardLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/utils/reviewer';
import { ArrowLeft, Fingerprint, Globe, Route, ScrollText, UserRound } from 'lucide-vue-next';

const props = defineProps({
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
});

const statusTone = computed(() => props.record.status_tone || 'bg-slate-100 text-slate-800 border-slate-200');

const prettyJson = (value) => {
  if (!value || (typeof value === 'object' && Object.keys(value).length === 0)) {
    return 'Tidak ada data.';
  }

  return JSON.stringify(value, null, 2);
};

const routeParamsJson = computed(() => prettyJson(props.record.route_params));
const queryPayloadJson = computed(() => prettyJson(props.record.query_payload));
const requestPayloadJson = computed(() => prettyJson(props.record.request_payload));
const responseMetaJson = computed(() => prettyJson(props.record.response_meta));
</script>

<template>
  <Head :title="`Admin - Activity Log #${record.id}`" />

  <AdminLayout title="Activity Log Detail">
    <div class="space-y-6">
      <section class="rounded-[28px] border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
              <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Audit Event</p>
              <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ record.action_label }}</h1>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                Detail event ini menyimpan actor, jalur request, route yang dieksekusi, serta payload yang sudah disanitasi untuk kebutuhan monitoring.
              </p>
              <div class="mt-4 flex flex-wrap items-center gap-2">
                <Badge variant="outline" class="border-slate-200 bg-slate-50 text-slate-700">{{ record.event_type_label }}</Badge>
                <Badge variant="outline" class="border-slate-200 bg-white text-slate-700">{{ record.workspace_label }}</Badge>
                <Badge variant="outline" class="border-slate-200 bg-white text-slate-700">{{ record.method }}</Badge>
                <Badge variant="outline" :class="statusTone">{{ record.status_code ?? '-' }}</Badge>
              </div>
            </div>

            <Button variant="outline" as-child>
              <Link :href="indexUrl" class="gap-2">
                <ArrowLeft class="h-4 w-4" />
                Kembali ke stream
              </Link>
            </Button>
          </div>
        </div>

        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Actor</p>
            <p class="mt-3 text-lg font-semibold text-slate-950">{{ record.actor.name }}</p>
            <p class="text-xs text-slate-500">{{ record.actor.email }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Occurred At</p>
            <p class="mt-3 text-lg font-semibold text-slate-950">{{ formatDateTime(record.occurred_at) }}</p>
            <p class="text-xs text-slate-500">{{ record.path }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Route Name</p>
            <p class="mt-3 text-lg font-semibold text-slate-950">{{ record.route_name }}</p>
            <p class="text-xs text-slate-500">{{ record.workspace_label }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Network</p>
            <p class="mt-3 text-lg font-semibold text-slate-950">{{ record.ip_address }}</p>
            <p class="text-xs text-slate-500 line-clamp-2">{{ record.user_agent }}</p>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-700">
                  <Route class="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Route context</CardTitle>
                  <CardDescription>Parameter route yang dibinding saat event ini diproses.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ routeParamsJson }}</pre>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-700">
                  <ScrollText class="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Request payload</CardTitle>
                  <CardDescription>Payload body yang sudah disanitasi sebelum disimpan sebagai jejak audit.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ requestPayloadJson }}</pre>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-700">
                  <UserRound class="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Actor profile</CardTitle>
                  <CardDescription>Role dan identitas user saat event dicatat.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Name</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ record.actor.name }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Email</p>
                <p class="mt-2 text-sm font-medium text-slate-950">{{ record.actor.email }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Roles</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <Badge
                    v-for="role in record.actor.roles"
                    :key="role"
                    variant="outline"
                    class="border-slate-200 bg-slate-50 text-slate-700"
                  >
                    {{ role }}
                  </Badge>
                  <span v-if="!record.actor.roles.length" class="text-sm text-slate-500">Tidak ada role.</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-700">
                  <Globe class="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Query & response</CardTitle>
                  <CardDescription>Query string dan metadata response untuk event ini.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-4">
              <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Query Payload</p>
                <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ queryPayloadJson }}</pre>
              </div>
              <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Response Meta</p>
                <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ responseMetaJson }}</pre>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <div class="flex items-center gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-2 text-slate-700">
                  <Fingerprint class="h-5 w-5" />
                </div>
                <div>
                  <CardTitle>Request signature</CardTitle>
                  <CardDescription>Fingerprint ringan untuk membantu tracing tanpa menyimpan data sensitif mentah.</CardDescription>
                </div>
              </div>
            </CardHeader>
            <CardContent class="space-y-3 text-sm text-slate-700">
              <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Path</p>
                <p class="mt-2 break-all font-medium text-slate-950">{{ record.path }}</p>
              </div>
              <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">User Agent</p>
                <p class="mt-2 break-words text-slate-700">{{ record.user_agent }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

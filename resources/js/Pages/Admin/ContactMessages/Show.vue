<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, ArrowLeft, Check, LoaderCircle, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAdminConfirmDialog } from '@/composables/useAdminConfirmDialog';
import { formatDateTime } from '@/utils/reviewer';

const props = defineProps({
  record: { type: Object, required: true },
  indexUrl: { type: String, required: true },
});

const { confirmDelete } = useAdminConfirmDialog();

const statusTone = (status) => {
  switch (status) {
    case 'new':
      return 'bg-amber-100 text-amber-900 border-amber-200';
    case 'in_progress':
      return 'bg-sky-100 text-sky-900 border-sky-200';
    case 'done':
      return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    case 'archived':
      return 'bg-slate-100 text-slate-800 border-slate-200';
    default:
      return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const runAction = async (type) => {
  const map = {
    in_progress: {
      method: 'post',
      url: route('admin.communications.contact-messages.in-progress', props.record.id),
      confirm: 'Tandai pesan ini sebagai in progress?',
    },
    done: {
      method: 'post',
      url: route('admin.communications.contact-messages.done', props.record.id),
      confirm: 'Tandai pesan ini sebagai selesai?',
    },
    archive: {
      method: 'post',
      url: route('admin.communications.contact-messages.archive', props.record.id),
      confirm: 'Arsipkan pesan ini?',
    },
    delete: {
      method: 'delete',
      url: route('admin.communications.contact-messages.destroy', props.record.id),
      confirm: 'Hapus pesan ini?',
    },
  };

  const action = map[type];
  if (!action) return;

  if (type === 'delete') {
    const confirmed = await confirmDelete({
      entityLabel: 'contact message',
      entityName: props.record.subject || props.record.name,
      description: `Anda akan menghapus contact message "${props.record.subject || props.record.name}". Aksi ini tidak dapat dibatalkan.`,
    });

    if (!confirmed) return;
  } else if (!window.confirm(action.confirm)) {
    return;
  }

  if (action.method === 'delete') {
    router.delete(action.url, { preserveScroll: true });
    return;
  }

  router.post(action.url, {}, { preserveScroll: true });
};
</script>

<template>
  <Head :title="`Admin - ${record.name}`" />

  <AdminLayout title="Detail Contact Message">
    <div class="mx-auto max-w-6xl space-y-6">
      <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Komunikasi</p>
          <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ record.name }}</h1>
          <p class="mt-2 text-sm text-slate-600">{{ record.email }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" as-child><Link :href="indexUrl"><ArrowLeft class="h-4 w-4" />Kembali ke daftar</Link></Button>
          <Button v-if="record.available_actions.in_progress" variant="outline" @click="runAction('in_progress')"><LoaderCircle class="h-4 w-4" />In Progress</Button>
          <Button v-if="record.available_actions.done" variant="outline" @click="runAction('done')"><Check class="h-4 w-4" />Done</Button>
          <Button v-if="record.available_actions.archive" variant="outline" @click="runAction('archive')"><Archive class="h-4 w-4" />Archive</Button>
          <Button variant="destructive" @click="runAction('delete')"><Trash2 class="h-4 w-4" />Hapus</Button>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Isi Pesan</CardTitle>
              <CardDescription>Konten pesan asli dari form kontak publik.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Subject</p>
                <p class="mt-2 text-sm text-slate-900">{{ record.subject || '(Tanpa subject)' }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Message</p>
                <div class="mt-2 whitespace-pre-wrap rounded-2xl border bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-800">
                  {{ record.message }}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Metadata Request</CardTitle>
              <CardDescription>Tracking dasar untuk audit asal pesan masuk.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Source</p>
                <p class="mt-1 text-sm text-slate-900">{{ record.source }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">IP Address</p>
                <p class="mt-1 text-sm text-slate-900">{{ record.ip_address }}</p>
              </div>
              <div class="md:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">User Agent</p>
                <p class="mt-1 break-all text-sm text-slate-900">{{ record.user_agent }}</p>
              </div>
            </CardContent>
          </Card>
        </div>

        <div class="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Tracking</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 text-sm text-slate-700">
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Status</p>
                <div class="mt-2">
                  <Badge variant="outline" :class="statusTone(record.status)">
                    {{ record.status_label }}
                  </Badge>
                </div>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Dibaca</p>
                <p class="mt-1">{{ formatDateTime(record.read_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Handled At</p>
                <p class="mt-1">{{ formatDateTime(record.handled_at) }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Handled By</p>
                <p class="mt-1">{{ record.handled_by_name }}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Masuk</p>
                <p class="mt-1">{{ formatDateTime(record.created_at) }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

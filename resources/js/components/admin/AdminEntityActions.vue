<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAdminConfirmDialog } from '@/composables/useAdminConfirmDialog';

const props = defineProps({
  detailHref: {
    type: String,
    default: null,
  },
  editHref: {
    type: String,
    default: null,
  },
  deleteUrl: {
    type: String,
    default: null,
  },
  detailLabel: {
    type: String,
    default: 'Detail',
  },
  editLabel: {
    type: String,
    default: 'Edit',
  },
  deleteLabel: {
    type: String,
    default: 'Hapus',
  },
  entityLabel: {
    type: String,
    default: 'data',
  },
  entityName: {
    type: String,
    default: '',
  },
});

const { confirmDelete } = useAdminConfirmDialog();

const hasActions = computed(() => Boolean(props.detailHref || props.editHref || props.deleteUrl));

const deleteDescription = computed(() => {
  const baseName = props.entityName ? `${props.entityLabel} "${props.entityName}"` : props.entityLabel;
  return `Anda akan menghapus ${baseName}. Aksi ini tidak dapat dibatalkan.`;
});

const runDelete = async () => {
  if (!props.deleteUrl) {
    return;
  }

  const confirmed = await confirmDelete({
    entityLabel: props.entityLabel,
    entityName: props.entityName,
    description: deleteDescription.value,
  });

  if (!confirmed) {
    return;
  }

  router.delete(props.deleteUrl, {
    preserveScroll: true,
    preserveState: true,
  });
};
</script>

<template>
  <div v-if="hasActions" class="flex items-center justify-end gap-2">
    <div class="hidden flex-wrap gap-2 md:flex">
      <Button v-if="detailHref" variant="outline" size="sm" as-child>
        <Link :href="detailHref">
          <Eye class="h-4 w-4" />
          <span>{{ detailLabel }}</span>
        </Link>
      </Button>

      <Button v-if="editHref" variant="outline" size="sm" as-child>
        <Link :href="editHref">
          <Pencil class="h-4 w-4" />
          <span>{{ editLabel }}</span>
        </Link>
      </Button>

      <Button v-if="deleteUrl" variant="destructive" size="sm" @click="runDelete">
        <Trash2 class="h-4 w-4" />
        <span>{{ deleteLabel }}</span>
      </Button>
    </div>

    <DropdownMenu v-if="hasActions">
      <DropdownMenuTrigger as-child>
        <Button variant="outline" size="icon-sm" class="md:hidden">
          <MoreHorizontal class="h-4 w-4" />
          <span class="sr-only">Buka aksi</span>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" class="w-44">
        <DropdownMenuItem v-if="detailHref" as-child>
          <Link :href="detailHref" class="flex items-center gap-2">
            <Eye class="h-4 w-4" />
            <span>{{ detailLabel }}</span>
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem v-if="editHref" as-child>
          <Link :href="editHref" class="flex items-center gap-2">
            <Pencil class="h-4 w-4" />
            <span>{{ editLabel }}</span>
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem
          v-if="deleteUrl"
          class="flex items-center gap-2 text-red-600 focus:text-red-700"
          @select.prevent="runDelete"
        >
          <Trash2 class="h-4 w-4" />
          <span>{{ deleteLabel }}</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>

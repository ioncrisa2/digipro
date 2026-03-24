<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import { Table } from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import ImageUpload from '@/components/admin/ImageUpload.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Strikethrough,
  List,
  ListOrdered,
  Quote,
  Minus,
  ImagePlus,
  Heading1,
  Heading2,
  Heading3,
  Link as LinkIcon,
  Image as ImageIcon,
  Table2,
  Rows3,
  Columns3,
  Undo2,
  Redo2,
  Eraser,
  Trash2,
} from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: 'Konten',
  },
  placeholder: {
    type: String,
    default: 'Mulai tulis konten...',
  },
  error: {
    type: String,
    default: '',
  },
  help: {
    type: String,
    default: '',
  },
  id: {
    type: String,
    default: 'rich_text_editor',
  },
  imageUploadUrl: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);
const isLinkDialogOpen = ref(false);
const isImageDialogOpen = ref(false);
const linkUrl = ref('');
const openInNewTab = ref(false);
const imageFile = ref(null);
const imageAlt = ref('');
const imageError = ref('');
const isUploadingImage = ref(false);
const hasImageUpload = computed(() => Boolean(props.imageUploadUrl));
const imageSizeOptions = [
  { value: '33%', label: 'Kecil' },
  { value: '66%', label: 'Sedang' },
  { value: '100%', label: 'Penuh' },
];

const RichImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      width: {
        default: '100%',
        renderHTML: (attributes) => ({
          style: `width: ${attributes.width ?? '100%'};`,
        }),
      },
    };
  },
});

async function uploadImageFile(file, alt = '') {
  if (!file || !hasImageUpload.value) {
    throw new Error('Upload gambar belum tersedia.');
  }

  const formData = new FormData();
  formData.append('image', file);
  formData.append('alt', alt);

  const response = await axios.post(props.imageUploadUrl, formData, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'multipart/form-data',
    },
  });

  const imageUrl = response.data?.url;
  if (!imageUrl) {
    throw new Error('URL gambar tidak ditemukan.');
  }

  return {
    url: imageUrl,
    alt: response.data?.alt || alt || null,
  };
}

const editor = new Editor({
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3],
      },
    }),
    Table.configure({
      resizable: true,
      HTMLAttributes: {
        class: 'admin-richtext-table',
      },
    }),
    TableRow,
    TableHeader,
    TableCell,
    RichImage.configure({
      HTMLAttributes: {
        class: 'rounded-xl my-6 h-auto',
      },
    }),
    Underline,
    Link.configure({
      openOnClick: false,
      autolink: true,
      protocols: ['http', 'https', 'mailto'],
      defaultProtocol: 'https',
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
    }),
  ],
  content: props.modelValue || '',
  editorProps: {
    attributes: {
      class:
        'admin-richtext prose prose-slate max-w-none min-h-[320px] px-4 py-3 focus:outline-none',
    },
    handleDrop(view, event) {
      const files = Array.from(event.dataTransfer?.files ?? []).filter((file) => file.type.startsWith('image/'));

      if (!hasImageUpload.value || files.length === 0) {
        return false;
      }

      event.preventDefault();

      const coordinates = view.posAtCoords({ left: event.clientX, top: event.clientY });
      if (coordinates) {
        editor.chain().focus().setTextSelection(coordinates.pos).run();
      }

      imageError.value = '';

      void uploadImageFile(files[0]).then((payload) => {
        editor.chain().focus().setImage({
          src: payload.url,
          alt: payload.alt,
          width: '100%',
        }).run();
      }).catch((error) => {
        imageError.value = error?.response?.data?.errors?.image?.[0]
          || error?.response?.data?.message
          || error?.message
          || 'Upload gambar gagal. Coba lagi.';
      });

      return true;
    },
    handlePaste(_view, event) {
      const files = Array.from(event.clipboardData?.files ?? []).filter((file) => file.type.startsWith('image/'));

      if (!hasImageUpload.value || files.length === 0) {
        return false;
      }

      event.preventDefault();
      imageError.value = '';

      void uploadImageFile(files[0]).then((payload) => {
        editor.chain().focus().setImage({
          src: payload.url,
          alt: payload.alt,
          width: '100%',
        }).run();
      }).catch((error) => {
        imageError.value = error?.response?.data?.errors?.image?.[0]
          || error?.response?.data?.message
          || error?.message
          || 'Upload gambar gagal. Coba lagi.';
      });

      return true;
    },
  },
  onUpdate: ({ editor: currentEditor }) => {
    emit('update:modelValue', currentEditor.getHTML());
  },
});

watch(
  () => props.modelValue,
  (value) => {
    if (!editor || editor.isDestroyed) {
      return;
    }

    const nextValue = value || '';
    if (nextValue === editor.getHTML()) {
      return;
    }

    editor.commands.setContent(nextValue, { emitUpdate: false });
  },
);

onBeforeUnmount(() => {
  editor.destroy();
});

const toolbarButtons = computed(() => [
  {
    key: 'bold',
    label: 'Bold',
    icon: Bold,
    action: () => editor.chain().focus().toggleBold().run(),
    active: () => editor.isActive('bold'),
    disabled: () => !editor.can().chain().focus().toggleBold().run(),
  },
  {
    key: 'italic',
    label: 'Italic',
    icon: Italic,
    action: () => editor.chain().focus().toggleItalic().run(),
    active: () => editor.isActive('italic'),
    disabled: () => !editor.can().chain().focus().toggleItalic().run(),
  },
  {
    key: 'underline',
    label: 'Underline',
    icon: UnderlineIcon,
    action: () => editor.chain().focus().toggleUnderline().run(),
    active: () => editor.isActive('underline'),
    disabled: () => !editor.can().chain().focus().toggleUnderline().run(),
  },
  {
    key: 'strike',
    label: 'Strike',
    icon: Strikethrough,
    action: () => editor.chain().focus().toggleStrike().run(),
    active: () => editor.isActive('strike'),
    disabled: () => !editor.can().chain().focus().toggleStrike().run(),
  },
  {
    key: 'heading1',
    label: 'H1',
    icon: Heading1,
    action: () => editor.chain().focus().toggleHeading({ level: 1 }).run(),
    active: () => editor.isActive('heading', { level: 1 }),
    disabled: () => !editor.can().chain().focus().toggleHeading({ level: 1 }).run(),
  },
  {
    key: 'heading2',
    label: 'H2',
    icon: Heading2,
    action: () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
    active: () => editor.isActive('heading', { level: 2 }),
    disabled: () => !editor.can().chain().focus().toggleHeading({ level: 2 }).run(),
  },
  {
    key: 'heading3',
    label: 'H3',
    icon: Heading3,
    action: () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
    active: () => editor.isActive('heading', { level: 3 }),
    disabled: () => !editor.can().chain().focus().toggleHeading({ level: 3 }).run(),
  },
  {
    key: 'bulletList',
    label: 'Bullet List',
    icon: List,
    action: () => editor.chain().focus().toggleBulletList().run(),
    active: () => editor.isActive('bulletList'),
    disabled: () => !editor.can().chain().focus().toggleBulletList().run(),
  },
  {
    key: 'orderedList',
    label: 'Ordered List',
    icon: ListOrdered,
    action: () => editor.chain().focus().toggleOrderedList().run(),
    active: () => editor.isActive('orderedList'),
    disabled: () => !editor.can().chain().focus().toggleOrderedList().run(),
  },
  {
    key: 'blockquote',
    label: 'Quote',
    icon: Quote,
    action: () => editor.chain().focus().toggleBlockquote().run(),
    active: () => editor.isActive('blockquote'),
    disabled: () => !editor.can().chain().focus().toggleBlockquote().run(),
  },
  {
    key: 'horizontalRule',
    label: 'Divider',
    icon: Minus,
    action: () => editor.chain().focus().setHorizontalRule().run(),
    active: () => false,
    disabled: () => !editor.can().chain().focus().setHorizontalRule().run(),
  },
  {
    key: 'insertTable',
    label: 'Table',
    icon: Table2,
    action: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
    active: () => editor.isActive('table'),
    disabled: () => !editor.can().chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
  },
]);

const applyLink = () => {
  const currentLinkAttributes = editor.getAttributes('link');
  linkUrl.value = currentLinkAttributes.href ?? '';
  openInNewTab.value = currentLinkAttributes.target === '_blank';
  isLinkDialogOpen.value = true;
};

const clearFormatting = () => {
  editor.chain().focus().unsetAllMarks().clearNodes().run();
};

const closeLinkDialog = () => {
  isLinkDialogOpen.value = false;
};

const saveLink = () => {
  const trimmed = linkUrl.value.trim();

  if (trimmed === '') {
    editor.chain().focus().unsetLink().run();
    closeLinkDialog();
    return;
  }

  editor.chain().focus().extendMarkRange('link').setLink({
    href: trimmed,
    target: openInNewTab.value ? '_blank' : null,
    rel: openInNewTab.value ? 'noopener noreferrer' : null,
  }).run();
  closeLinkDialog();
};

const removeLink = () => {
  editor.chain().focus().unsetLink().run();
  linkUrl.value = '';
  openInNewTab.value = false;
  closeLinkDialog();
};

const openImageDialog = () => {
  if (!hasImageUpload.value) {
    return;
  }

  imageFile.value = null;
  imageAlt.value = '';
  imageError.value = '';
  isImageDialogOpen.value = true;
};

const closeImageDialog = () => {
  isImageDialogOpen.value = false;
  imageFile.value = null;
  imageAlt.value = '';
  imageError.value = '';
};

const uploadImage = async () => {
  if (!imageFile.value || !hasImageUpload.value) {
    imageError.value = 'Pilih gambar terlebih dahulu.';
    return;
  }

  isUploadingImage.value = true;
  imageError.value = '';

  try {
    const payload = await uploadImageFile(imageFile.value, imageAlt.value);

    editor.chain().focus().setImage({
      src: payload.url,
      alt: payload.alt,
      width: '100%',
    }).run();

    closeImageDialog();
  } catch (error) {
    imageError.value = error?.response?.data?.errors?.image?.[0]
      || error?.response?.data?.message
      || 'Upload gambar gagal. Coba lagi.';
  } finally {
    isUploadingImage.value = false;
  }
};

const selectedImageWidth = computed(() => {
  if (!editor.isActive('image')) {
    return null;
  }

  return editor.getAttributes('image').width ?? '100%';
});

const setSelectedImageWidth = (width) => {
  editor.chain().focus().updateAttributes('image', { width }).run();
};

const tableActions = [
  {
    key: 'addColumn',
    label: 'Kolom',
    icon: Columns3,
    action: () => editor.chain().focus().addColumnAfter().run(),
  },
  {
    key: 'addRow',
    label: 'Baris',
    icon: Rows3,
    action: () => editor.chain().focus().addRowAfter().run(),
  },
  {
    key: 'toggleHeader',
    label: 'Header',
    icon: Table2,
    action: () => editor.chain().focus().toggleHeaderRow().run(),
  },
  {
    key: 'deleteColumn',
    label: 'Hapus Kolom',
    icon: Columns3,
    action: () => editor.chain().focus().deleteColumn().run(),
  },
  {
    key: 'deleteRow',
    label: 'Hapus Baris',
    icon: Rows3,
    action: () => editor.chain().focus().deleteRow().run(),
  },
  {
    key: 'deleteTable',
    label: 'Hapus Tabel',
    icon: Trash2,
    action: () => editor.chain().focus().deleteTable().run(),
  },
];
</script>

<template>
  <div class="space-y-3">
    <Label :for="id">{{ label }}</Label>

    <div class="rounded-2xl border bg-white shadow-sm">
      <div class="flex flex-wrap gap-2 border-b bg-slate-50/80 px-3 py-3">
        <Button
          v-for="button in toolbarButtons"
          :key="button.key"
          type="button"
          size="sm"
          :variant="button.active() ? 'default' : 'outline'"
          :disabled="button.disabled()"
          class="h-9 px-3"
          @click="button.action()"
        >
          <component :is="button.icon" class="mr-2 h-4 w-4" />
          <span class="hidden sm:inline">{{ button.label }}</span>
        </Button>

        <Button
          type="button"
          size="sm"
          :variant="editor.isActive('link') ? 'default' : 'outline'"
          class="h-9 px-3"
          @click="applyLink"
        >
          <LinkIcon class="mr-2 h-4 w-4" />
          <span class="hidden sm:inline">Link</span>
        </Button>

        <Button
          v-if="hasImageUpload"
          type="button"
          size="sm"
          variant="outline"
          class="h-9 px-3"
          @click="openImageDialog"
        >
          <ImagePlus class="mr-2 h-4 w-4" />
          <span class="hidden sm:inline">Gambar</span>
        </Button>

        <div v-if="editor.isActive('image')" class="flex flex-wrap gap-2">
          <Button
            v-for="option in imageSizeOptions"
            :key="option.value"
            type="button"
            size="sm"
            :variant="selectedImageWidth === option.value ? 'default' : 'outline'"
            class="h-9 px-3"
            @click="setSelectedImageWidth(option.value)"
          >
            <ImageIcon class="mr-2 h-4 w-4" />
            <span class="hidden sm:inline">{{ option.label }}</span>
          </Button>
        </div>

        <div v-if="editor.isActive('table')" class="flex flex-wrap gap-2">
          <Button
            v-for="action in tableActions"
            :key="action.key"
            type="button"
            size="sm"
            variant="outline"
            class="h-9 px-3"
            @click="action.action()"
          >
            <component :is="action.icon" class="mr-2 h-4 w-4" />
            <span class="hidden sm:inline">{{ action.label }}</span>
          </Button>
        </div>

        <Button type="button" size="sm" variant="outline" class="h-9 px-3" @click="clearFormatting">
          <Eraser class="mr-2 h-4 w-4" />
          <span class="hidden sm:inline">Clear</span>
        </Button>

        <div class="ml-auto flex flex-wrap gap-2">
          <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-9 px-3"
            :disabled="!editor.can().chain().focus().undo().run()"
            @click="editor.chain().focus().undo().run()"
          >
            <Undo2 class="mr-2 h-4 w-4" />
            <span class="hidden sm:inline">Undo</span>
          </Button>

          <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-9 px-3"
            :disabled="!editor.can().chain().focus().redo().run()"
            @click="editor.chain().focus().redo().run()"
          >
            <Redo2 class="mr-2 h-4 w-4" />
            <span class="hidden sm:inline">Redo</span>
          </Button>
        </div>
      </div>

      <EditorContent :id="id" :editor="editor" />
    </div>

    <p v-if="help" class="text-xs text-slate-500">{{ help }}</p>
    <p v-if="hasImageUpload" class="text-xs text-slate-500">
      Anda juga bisa drag-and-drop atau paste gambar langsung ke editor.
    </p>
    <p v-if="error" class="text-xs text-rose-600">{{ error }}</p>
    <p v-if="imageError" class="text-xs text-rose-600">{{ imageError }}</p>

    <Dialog v-model:open="isLinkDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Atur Link</DialogTitle>
          <DialogDescription>
            Masukkan URL tujuan untuk teks yang sedang dipilih. Kosongkan untuk melepas link.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-2">
          <Label :for="`${id}_link_url`">URL</Label>
          <Input
            :id="`${id}_link_url`"
            v-model="linkUrl"
            placeholder="https://contoh.com"
            @keyup.enter="saveLink"
          />
        </div>

        <label class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm text-slate-700">
          <Checkbox :model-value="openInNewTab" @update:model-value="openInNewTab = Boolean($event)" />
          <span>Buka di tab baru</span>
        </label>

        <DialogFooter class="gap-2 sm:justify-between">
          <Button type="button" variant="outline" @click="removeLink">Hapus Link</Button>
          <div class="flex gap-2">
            <Button type="button" variant="outline" @click="closeLinkDialog">Batal</Button>
            <Button type="button" @click="saveLink">Simpan</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="isImageDialogOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Upload Gambar</DialogTitle>
          <DialogDescription>
            Unggah gambar untuk disisipkan ke konten. Preview akan tampil sebelum gambar dimasukkan ke editor.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
          <div class="space-y-2">
            <Label :for="`${id}_image_alt`">Alt Text</Label>
            <Input
              :id="`${id}_image_alt`"
              v-model="imageAlt"
              placeholder="Deskripsi singkat gambar"
            />
          </div>

          <ImageUpload
            v-model="imageFile"
            :multiple="false"
            :loading="isUploadingImage"
            title="Upload gambar"
            description="Pilih satu gambar, drag-and-drop, atau paste file gambar ke editor."
          />

          <p v-if="imageError" class="text-xs text-rose-600">{{ imageError }}</p>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="closeImageDialog">Batal</Button>
          <Button type="button" :disabled="isUploadingImage" @click="uploadImage">
            {{ isUploadingImage ? 'Mengunggah...' : 'Sisipkan Gambar' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.admin-richtext > * + *) {
  margin-top: 0.85rem;
}

:deep(.admin-richtext h2) {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.3;
}

:deep(.admin-richtext h1) {
  font-size: 1.9rem;
  font-weight: 800;
  line-height: 1.2;
}

:deep(.admin-richtext h3) {
  font-size: 1.2rem;
  font-weight: 650;
  line-height: 1.35;
}

:deep(.admin-richtext ul) {
  list-style: disc;
  padding-left: 1.5rem;
}

:deep(.admin-richtext ol) {
  list-style: decimal;
  padding-left: 1.5rem;
}

:deep(.admin-richtext blockquote) {
  border-left: 3px solid rgb(148 163 184);
  padding-left: 1rem;
  color: rgb(71 85 105);
}

:deep(.admin-richtext hr) {
  margin: 1.25rem 0;
  border: 0;
  border-top: 1px solid rgb(226 232 240);
}

:deep(.admin-richtext a) {
  color: rgb(30 64 175);
  text-decoration: underline;
}

:deep(.admin-richtext img) {
  display: block;
  max-width: 100%;
  margin-inline: auto;
}

:deep(.admin-richtext table) {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  margin: 1.5rem 0;
}

:deep(.admin-richtext th),
:deep(.admin-richtext td) {
  border: 1px solid rgb(226 232 240);
  padding: 0.75rem;
  vertical-align: top;
}

:deep(.admin-richtext th) {
  background: rgb(248 250 252);
  font-weight: 700;
}

:deep(.admin-richtext p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: rgb(148 163 184);
  float: left;
  height: 0;
  pointer-events: none;
}
</style>

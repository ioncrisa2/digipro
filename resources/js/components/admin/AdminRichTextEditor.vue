<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
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
  Heading2,
  Heading3,
  Link as LinkIcon,
  Image as ImageIcon,
  Undo2,
  Redo2,
  Eraser,
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
const imagePreviewUrl = ref('');
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
        levels: [2, 3],
      },
    }),
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
  if (imagePreviewUrl.value && imagePreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }

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

const revokeImagePreview = () => {
  if (imagePreviewUrl.value && imagePreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
};

const openImageDialog = () => {
  if (!hasImageUpload.value) {
    return;
  }

  revokeImagePreview();
  imageFile.value = null;
  imageAlt.value = '';
  imagePreviewUrl.value = '';
  imageError.value = '';
  isImageDialogOpen.value = true;
};

const closeImageDialog = () => {
  isImageDialogOpen.value = false;
  imageFile.value = null;
  imageAlt.value = '';
  imageError.value = '';
  revokeImagePreview();
  imagePreviewUrl.value = '';
};

const onImageSelected = (event) => {
  const file = event.target.files?.[0] ?? null;

  revokeImagePreview();
  imageFile.value = file;
  imageError.value = '';
  imagePreviewUrl.value = file ? URL.createObjectURL(file) : '';
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
            <Label :for="`${id}_image_file`">File Gambar</Label>
            <Input
              :id="`${id}_image_file`"
              type="file"
              accept="image/*"
              @change="onImageSelected"
            />
          </div>

          <div class="space-y-2">
            <Label :for="`${id}_image_alt`">Alt Text</Label>
            <Input
              :id="`${id}_image_alt`"
              v-model="imageAlt"
              placeholder="Deskripsi singkat gambar"
            />
          </div>

          <div v-if="imagePreviewUrl" class="space-y-2">
            <Label>Preview</Label>
            <div class="overflow-hidden rounded-2xl border bg-slate-50 p-2">
              <img :src="imagePreviewUrl" alt="Preview gambar artikel" class="max-h-72 w-full rounded-xl object-contain" />
            </div>
          </div>

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

:deep(.admin-richtext p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: rgb(148 163 184);
  float: left;
  height: 0;
  pointer-events: none;
}
</style>

<script setup>
import { VueFilesPreview } from 'vue-files-preview';
import 'vue-files-preview/lib/style.css';

defineProps({
  url: {
    type: String,
    default: '',
  },
  name: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <VueFilesPreview
    :url="url"
    :name="name"
    width="100%"
    height="100%"
    overflow="auto"
  />
</template>

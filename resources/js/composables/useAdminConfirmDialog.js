import { useDialogStore } from '@/stores/dialogStore';

const wrapEntityLabel = (label, name) => {
  if (!name) {
    return label;
  }

  return `${label} "${name}"`;
};

export const useAdminConfirmDialog = () => {
  const dialog = useDialogStore();

  const confirmDelete = async ({
    entityLabel = 'data',
    entityName = '',
    title = 'Konfirmasi Hapus',
    description,
    confirmText = 'Hapus',
    cancelText = 'Batal',
  } = {}) => {
    return dialog.confirmDestruct({
      title,
      description: description ?? `Anda akan menghapus ${wrapEntityLabel(entityLabel, entityName)}. Aksi ini tidak dapat dibatalkan.`,
      confirmText,
      cancelText,
    });
  };

  return {
    confirmDelete,
  };
};

import { defineStore } from "pinia";

export const useDialogStore = defineStore("dialog", {
    state: () => ({
        isOpen: false,
        type: "confirm",
        title: "",
        description: "",
        confirmText: "OK",
        cancelText: "Batal",
        variant: "default",
        isProcessing: false,
        resolveCallback: null,
        rejectCallback: null,
    }),

    getters: {
        showCancel: (state) => state.type === "confirm",
        confirmButtonClass: (state) => {
            if (state.variant === "destructive") {
                return "bg-destructive hover:bg-destructive/90 text-destructive-foreground";
            }
            return "";
        },
    },

    actions: {
        /**
         * Show confirmation dialog
         * @param {Object} options
         * @param {string} options.title - Dialog title
         * @param {string} options.description - Dialog description
         * @param {string} options.confirmText - Confirm button text
         * @param {string} options.cancelText - Cancel button text
         * @param {string} options.variant - 'default' | 'destructive'
         * @returns {Promise<boolean>}
         */
        confirm(options = {}) {
            this.type = "confirm";
            this.title = options.title || "Konfirmasi";
            this.description = options.description || "Apakah Anda yakin?";
            this.confirmText = options.confirmText || "Ya";
            this.cancelText = options.cancelText || "Batal";
            this.variant = options.variant || "default";
            this.isProcessing = false;
            this.isOpen = true;

            return new Promise((resolve, reject) => {
                this.resolveCallback = resolve;
                this.rejectCallback = reject;
            });
        },

        /**
         * Show alert dialog (no cancel button)
         * @param {Object} options
         * @param {string} options.title - Dialog title
         * @param {string} options.description - Dialog description
         * @param {string} options.confirmText - Confirm button text
         * @param {string} options.variant - 'default' | 'destructive'
         * @returns {Promise<boolean>}
         */
        alert(options = {}) {
            this.type = "alert";
            this.title = options.title || "Informasi";
            this.description = options.description || "";
            this.confirmText = options.confirmText || "OK";
            this.variant = options.variant || "default";
            this.isProcessing = false;
            this.isOpen = true;

            return new Promise((resolve, reject) => {
                this.resolveCallback = resolve;
                this.rejectCallback = reject;
            });
        },

        /**
         * Show destructive confirmation (red button)
         * Shorthand for confirm with destructive variant
         */
        confirmDestruct(options = {}) {
            return this.confirm({
                ...options,
                variant: "destructive",
            });
        },

        /**
         * Async confirm - for actions that take time
         * Shows loading state on confirm button
         * @param {Object} options - Same as confirm()
         * @param {Function} options.onConfirm - Async function to execute
         * @returns {Promise<boolean>}
         */
        async confirmAsync(options = {}) {
            const { onConfirm, ...dialogOptions } = options;

            this.confirm(dialogOptions);

            return new Promise((resolve, reject) => {
                const originalResolve = this.resolveCallback;

                this.resolveCallback = async (value) => {
                    if (value && onConfirm) {
                        this.isProcessing = true;
                        try {
                            await onConfirm();
                            this.close();
                            resolve(true);
                        } catch (error) {
                            console.error("Dialog async action failed:", error);
                            this.isProcessing = false;
                            reject(error);
                        }
                    } else {
                        this.close();
                        resolve(false);
                    }
                };
            });
        },

        handleConfirm() {
            if (this.resolveCallback && !this.isProcessing) {
                this.resolveCallback(true);

                // Only close immediately if not processing
                if (!this.isProcessing) {
                    this.close();
                }
            }
        },

        handleCancel() {
            if (this.resolveCallback && !this.isProcessing) {
                this.resolveCallback(false);
                this.close();
            }
        },

        close() {
            this.isOpen = false;
            this.isProcessing = false;
            this.resolveCallback = null;
            this.rejectCallback = null;
        },
    },
});

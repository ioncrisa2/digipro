// stores/appraisalFormStore.js
import { defineStore } from 'pinia';
import { router } from '@inertiajs/vue3';

const FORM_SESSION_ID_KEY = 'appraisal_form_session_id';
const STORAGE_PREFIX = 'appraisal_form_session_';
const ORPHAN_MAX_AGE_HOURS = 12;

function safeUUID() {
    try {
        return (crypto?.randomUUID?.() ?? `${Date.now()}_${Math.random().toString(16).slice(2)}`);
    } catch (_) {
        return `${Date.now()}_${Math.random().toString(16).slice(2)}`;
    }
}

export const useAppraisalFormStore = defineStore('appraisalForm', {
    state: () => ({
        currentStep: 1,

        // per-tab session id (diset saat halaman Create mount)
        _sessionId: null,

        form: {
            purpose: '',
            reportType: 'terinci',
            needsHardCopy: false,
            physicalCopiesCount: 0,
            docNpwp: null,
            docRepresentative: null,
            docPermission: null,
            assets: [],
        },

        // Asset form state
        isAssetFormOpen: false,
        editingIndex: null,
        editingAsset: null,

        // UI state
        isSubmitting: false,
        lastSavedAt: null,
    }),

    getters: {
        steps: () => ['General Info', 'Asset Portofolio', 'Review'],

        hasAssets: (state) => state.form.assets.length > 0,

        assetCount: (state) => state.form.assets.length,

        isStep1Valid: (state) => {
            if (!state.form.purpose) return false;

            if (state.form.purpose === 'jual_beli') {
                return !!(state.form.docNpwp && state.form.docRepresentative);
            }

            if (state.form.purpose === 'penjaminan_utang') {
                return !!state.form.docNpwp;
            }

            if (state.form.purpose === 'lelang') {
                return !!state.form.docPermission;
            }

            return true;
        },

        canProceedToStep2: (state) => {
            return state.isStep1Valid;
        },

        canProceedToStep3: (state) => {
            return state.form.assets.length > 0;
        },

        canSubmit() {
            return this.isStep1Valid && this.hasAssets && !this.isSubmitting;
        },

        // Serialize form for draft saving (exclude File objects)
        serializableForm: (state) => {
            const serialized = {
                ...state.form,
                docNpwp: state.form.docNpwp?.name || null,
                docRepresentative: state.form.docRepresentative?.name || null,
                docPermission: state.form.docPermission?.name || null,
                assets: state.form.assets.map(asset => ({
                    ...asset,
                    // Keep file names for reference, not the File objects
                    docPbb: asset.docPbb?.name || null,
                    docImb: asset.docImb?.name || null,
                    docOldReport: asset.docOldReport?.name || null,
                    docCerts: asset.docCerts?.map(f => f.name) || [],
                    photos: asset.photos?.map(f => f.name) || [],
                }))
            };
            return serialized;
        }
    },

    actions: {
        // Navigation
        nextStep() {
            if (this.currentStep === 1 && !this.validateStep1()) {
                return false;
            }

            if (this.currentStep === 2 && !this.canProceedToStep3) {
                return false;
            }

            if (this.currentStep < 3) {
                this.currentStep++;
                window.scrollTo(0, 0);
                this.saveProgress();
            }

            return true;
        },

        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                window.scrollTo(0, 0);
            }
        },

        goToStep(step) {
            if (step >= 1 && step <= 3) {
                this.currentStep = step;
                window.scrollTo(0, 0);
            }
        },

        // Validation
        validateStep1() {
            if (!this.form.purpose) {
                return { valid: false, message: 'Pilih tujuan penilaian terlebih dahulu' };
            }

            if (this.form.purpose === 'jual_beli') {
                if (!this.form.docNpwp) {
                    return { valid: false, message: 'Mohon upload NPWP' };
                }
                if (!this.form.docRepresentative) {
                    return { valid: false, message: 'Mohon upload Surat Representatif' };
                }
            } else if (this.form.purpose === 'penjaminan_utang') {
                if (!this.form.docNpwp) {
                    return { valid: false, message: 'Mohon upload NPWP' };
                }
            } else if (this.form.purpose === 'lelang') {
                if (!this.form.docPermission) {
                    return { valid: false, message: 'Mohon upload Surat Izin Kepemilikan Aset' };
                }
            }

            if (this.form.needsHardCopy && Number(this.form.physicalCopiesCount) < 1) {
                return { valid: false, message: 'Jumlah salinan fisik minimal 1' };
            }

            return { valid: true };
        },

        // Form mutations
        updatePurpose(purpose) {
            this.form.purpose = purpose;
            this.resetGlobalDocs();
            this.saveProgress();
        },

        updateReportType(type) {
            this.form.reportType = type;
            this.saveProgress();
        },

        toggleHardCopy(value) {
            this.form.needsHardCopy = value;
            if (!value) {
                this.form.physicalCopiesCount = 0;
            } else if (!this.form.physicalCopiesCount || this.form.physicalCopiesCount < 1) {
                this.form.physicalCopiesCount = 1;
            }
            this.saveProgress();
        },

        updatePhysicalCopies(count) {
            this.form.physicalCopiesCount = Math.max(0, Number(count) || 0);
            this.saveProgress();
        },

        setDocument(field, file) {
            this.form[field] = file;
            this.saveProgress();
        },

        resetGlobalDocs() {
            this.form.docNpwp = null;
            this.form.docRepresentative = null;
            this.form.docPermission = null;
        },

        // Asset management
        openAddAsset() {
            this.editingIndex = null;
            this.editingAsset = null;
            this.isAssetFormOpen = true;
            window.scrollTo(0, 0);
        },

        openEditAsset(index) {
            if (index >= 0 && index < this.form.assets.length) {
                this.editingIndex = index;
                this.editingAsset = { ...this.form.assets[index] }; // Clone for editing
                this.isAssetFormOpen = true;
                window.scrollTo(0, 0);
            }
        },

        closeAssetForm() {
            this.isAssetFormOpen = false;
            this.editingIndex = null;
            this.editingAsset = null;
        },

        upsertAsset(assetData) {
            if (this.editingIndex === null) {
                // Add new
                this.form.assets.push(assetData);
            } else {
                // Update existing
                this.form.assets[this.editingIndex] = assetData;
            }

            this.closeAssetForm();
            this.saveProgress();

            return {
                valid: true,
                message: this.editingIndex === null ? 'Asset added to portfolio' : 'Asset updated'
            };
        },

        removeAsset(index) {
            if (index >= 0 && index < this.form.assets.length) {
                this.form.assets.splice(index, 1);
                this.saveProgress();
                return true;
            }
            return false;
        },

			// Progress storage (sementara, hanya selama user berada di halaman Create)
			initFormSession() {
				const existing = sessionStorage.getItem(FORM_SESSION_ID_KEY);
				const id = existing || safeUUID();
				sessionStorage.setItem(FORM_SESSION_ID_KEY, id);
				this._sessionId = id;
			},

			getStorageKey() {
				if (!this._sessionId) this.initFormSession();
				return `${STORAGE_PREFIX}${this._sessionId}`;
			},

			cleanupOrphanedSnapshots() {
				// Best-effort cleanup, tanpa mengganggu tab aktif.
				try {
					const now = Date.now();
					const maxAge = ORPHAN_MAX_AGE_HOURS * 60 * 60 * 1000;
					for (let i = 0; i < localStorage.length; i++) {
						const key = localStorage.key(i);
						if (!key || !key.startsWith(STORAGE_PREFIX)) continue;
						const raw = localStorage.getItem(key);
						if (!raw) continue;
						let parsed;
						try { parsed = JSON.parse(raw); } catch { continue; }
						const savedAt = Number(parsed?.savedAt ?? 0);
						if (savedAt && now - savedAt > maxAge) {
							localStorage.removeItem(key);
						}
					}
				} catch (_) {
					// ignore
				}
			},

			saveProgress() {
				try {
					const snapshot = {
						v: 1,
						savedAt: Date.now(),
						currentStep: this.currentStep,
						form: this.serializableForm,
					};
					localStorage.setItem(this.getStorageKey(), JSON.stringify(snapshot));
					this.lastSavedAt = new Date(snapshot.savedAt);
				} catch (error) {
					console.error('Failed to save progress:', error);
				}
			},

			loadProgress() {
				try {
					this.initFormSession();
					this.cleanupOrphanedSnapshots();
					const saved = localStorage.getItem(this.getStorageKey());
					if (!saved) return false;

					const snapshot = JSON.parse(saved);
					if (!snapshot || !snapshot.form) {
						this.clearProgress();
						return false;
					}

					this.currentStep = snapshot.currentStep || 1;

					// Restore non-file data only
					this.form.purpose = snapshot.form.purpose || '';
					this.form.reportType = snapshot.form.reportType || 'terinci';
					this.form.needsHardCopy = snapshot.form.needsHardCopy || false;
					this.form.physicalCopiesCount = snapshot.form.physicalCopiesCount || 0;
					this.form.assets = Array.isArray(snapshot.form.assets) ? snapshot.form.assets : [];

					this.lastSavedAt = new Date(snapshot.savedAt || Date.now());
					return true;
				} catch (error) {
					console.error('Failed to load progress:', error);
					this.clearProgress();
					return false;
				}
			},

			clearProgress() {
				try {
					if (this._sessionId) {
						localStorage.removeItem(`${STORAGE_PREFIX}${this._sessionId}`);
					}
					sessionStorage.removeItem(FORM_SESSION_ID_KEY);
					this._sessionId = null;
					this.lastSavedAt = null;
				} catch (error) {
					console.error('Failed to clear progress:', error);
				}
			},

        // Form submission
        async submitForm(notify) {
            const validation = this.validateStep1();
            if (!validation.valid) {
                notify('error', validation.message);
                return;
            }

            if (this.form.assets.length === 0) {
                notify('warning', 'Mohon tambahkan minimal satu aset');
                return;
            }

            this.isSubmitting = true;
            notify('info', 'Mengirim permohonan penilaian...');

            const payload = {
                purpose: this.form.purpose,
                report_type: this.form.reportType,
                report_format: this.form.needsHardCopy ? 'both' : 'digital',
                physical_copies_count: this.form.needsHardCopy
                    ? Math.max(1, Number(this.form.physicalCopiesCount || 1))
                    : 0,

                documents: {
                    npwp: this.form.docNpwp,
                    representative: this.form.docRepresentative,
                    permission: this.form.docPermission,
                },

                assets: this.form.assets.map(a => ({
                    type: a.type,
                    land_area: a.landArea,
                    building_area: a.buildingArea,
                    floors: a.floors,
                    renovation_year: a.renovationYear,
                    province_id: a.province,
                    regency_id: a.regency,
                    district_id: a.district,
                    village_id: a.village,
                    address: a.address,
                    coordinates: typeof a.coordinates === 'object' && a.coordinates !== null
                        ? JSON.stringify(a.coordinates)
                        : a.coordinates,
                    doc_pbb: a.docPbb,
                    doc_imb: a.docImb,
                    doc_old_report: a.docOldReport,
                    doc_certs: a.docCerts,
                    photos: a.photos,
                })),
            };

            const fd = new FormData();
            this.appendFormData(fd, payload);

            router.post(route('appraisal.store'), fd, {
                forceFormData: true,
                onSuccess: () => {
                    notify('success', 'Permohonan Berhasil Dikirim!');
                    this.resetForm();
                    router.visit('/dashboard');
                },
                onError: (error) => {
                    console.error('Submission error:', error);
                    notify('error', 'Gagal mengirim. Silakan cek kembali input Anda.');
                    this.isSubmitting = false;
                },
                onFinish: () => {
                    this.isSubmitting = false;
                }
            });
        },

        // Helper for FormData building
        appendFormData(fd, data, parentKey = '') {
            if (data === null || data === undefined) return;

            if (data instanceof File || data instanceof Blob) {
                fd.append(parentKey, data);
                return;
            }

            if (Array.isArray(data)) {
                data.forEach((val, i) => {
                    this.appendFormData(fd, val, `${parentKey}[${i}]`);
                });
                return;
            }

            if (typeof data === 'object') {
                Object.entries(data).forEach(([key, val]) => {
                    const fullKey = parentKey ? `${parentKey}[${key}]` : key;
                    this.appendFormData(fd, val, fullKey);
                });
                return;
            }

            fd.append(parentKey, String(data));
        },

        // Reset entire form
        resetForm({ clearStorage = true } = {}) {
            this.currentStep = 1;
            this.form = {
                purpose: '',
                reportType: 'terinci',
                needsHardCopy: false,
                physicalCopiesCount: 0,
                docNpwp: null,
                docRepresentative: null,
                docPermission: null,
                assets: [],
            };
            this.isAssetFormOpen = false;
            this.editingIndex = null;
            this.editingAsset = null;
            this.isSubmitting = false;
            if (clearStorage) this.clearProgress();
        },

        // Utility
        typeLabel(type) {
            const labels = {
                house: 'Rumah Tinggal',
                land: 'Tanah Kosong',
                shophouse: 'Ruko / Rukan',
                warehouse: 'Gudang / Pabrik',
            };
            return labels[type] || type;
        },
    },
});

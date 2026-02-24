import { defineStore } from "pinia";
import { useForm } from "@inertiajs/vue3";
import { useNotification } from "@/composables/useNotification";

export const useRegisterStore = defineStore("register", () => {
  const { notify } = useNotification();

  const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    terms: false,
  });

  const setTerms = (value) => {
    form.terms = !!value;
  };

  const handleRegister = () => {
    form.post("/register", {
      preserveScroll: true,
      onSuccess: () => {
        notify("success", "Akun dibuat. Silakan cek email untuk verifikasi sebelum login.");
        form.reset("password", "password_confirmation");
      },
      onError: () => {
        notify("error", "Please check the registration form again.");
      },
    });
  };

  const reset = () => {
    form.reset();
    form.clearErrors();
    form.terms = false;
  };

  return { form, setTerms, handleRegister, reset };
});

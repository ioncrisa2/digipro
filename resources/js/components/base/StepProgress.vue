<script setup>
import {
  Stepper,
  StepperItem,
  StepperSeparator,
  StepperTitle,
  StepperTrigger,
} from "@/components/ui/stepper";

defineProps({
  currentStep: { type: Number, required: true },
  steps: { type: Array, required: true },
});
</script>

<template>
  <Stepper :model-value="currentStep" class="w-full">
    <StepperItem
      v-for="(step, index) in steps"
      :key="index"
      :step="index + 1"
      class="relative flex flex-1 flex-col items-center gap-2"
    >
      <StepperSeparator
        v-if="index !== steps.length - 1"
        class="absolute left-1/2 top-5 -z-10 h-px w-full -translate-y-1/2 bg-border"
      />

      <StepperTrigger
        class="pointer-events-none flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold bg-background transition"
        :class="
          index + 1 < currentStep
            ? 'border-primary bg-primary text-primary-foreground'
            : index + 1 === currentStep
            ? 'border-primary text-primary ring-4 ring-primary/10'
            : 'border-muted-foreground/30 text-muted-foreground'
        "
      >
        <span v-if="index + 1 < currentStep">✓</span>
        <span v-else>{{ index + 1 }}</span>
      </StepperTrigger>

      <StepperTitle
        class="hidden text-xs font-semibold sm:block"
        :class="index + 1 <= currentStep ? 'text-foreground' : 'text-muted-foreground'"
      >
        {{ step }}
      </StepperTitle>
    </StepperItem>
  </Stepper>
</template>

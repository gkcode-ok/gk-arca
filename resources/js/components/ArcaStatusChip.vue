<template>
  <v-chip :color="color" variant="tonal" size="small" class="arca-status-chip">
    <v-icon start size="16">{{ icon }}</v-icon>
    {{ label }}
  </v-chip>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  state: { type: String, default: 'grey' }, // green | yellow | red | grey
  text: { type: String, default: '' },
});

const MAP = {
  green: { color: 'success', icon: 'mdi-check-circle' },
  yellow: { color: 'warning', icon: 'mdi-alert-circle' },
  red: { color: 'error', icon: 'mdi-close-circle' },
  grey: { color: 'grey', icon: 'mdi-help-circle-outline' },
};

const color = computed(() => (MAP[props.state] || MAP.grey).color);
const icon = computed(() => (MAP[props.state] || MAP.grey).icon);
const label = computed(() => props.text || '-');
</script>

<style scoped>
.arca-status-chip { font-weight: 600; }
</style>

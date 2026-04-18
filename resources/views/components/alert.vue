<script setup lang="ts">
const props = defineProps<AlertProps>();

interface AlertProps {
    type?: Enums.BannerType;
    title?: string;
    id?: string;
}

const isVisible = ref(true);

onMounted(() => {
    if (props.id) {
        const storedState = localStorage.getItem(`alert-${props.id}`);
        if (storedState === "closed") {
            isVisible.value = false;
        }
    }
});

const closeAlert = () => {
    isVisible.value = false;
    if (props.id) {
        localStorage.setItem(`alert-${props.id}`, "closed");
    }
};

const alertClasses = computed(() => {
    const styles: Partial<Record<Enums.BannerType, string>> = {
        info: "border-[#5d4d2f] bg-[#2a2418] text-yellow-100",
        default: "border-stone-800 bg-stone-950 text-white",
    };
    return styles[props.type || "default"];
});
</script>

<template>
    <div
        v-if="isVisible"
        :class="alertClasses"
        class="relative mb-4 flex flex-col gap-2 border-2 p-3"
    >
        <button
            v-if="id"
            class="absolute right-0 top-0 p-1 hover:opacity-70"
            aria-label="Close alert"
            @click="closeAlert"
        >
            <MkRemove class="size-5" />
        </button>

        <h2 v-if="title" class="font-bold">{{ title }}</h2>

        <slot></slot>
    </div>
</template>

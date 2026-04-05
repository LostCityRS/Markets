<script setup lang="ts">
import VueMarkdown from "vue-markdown-render";
import { Tooltip } from "floating-vue";

const props = defineProps<{
    banner: Data.Banner.BannerData;
}>();

const hasMetadata = computed(
    () => props.banner.eventAt || props.banner.detailsUrl,
);

const eventDate = computed(() =>
    props.banner.eventAt ? new Date(props.banner.eventAt) : null,
);

const localTimeFormatted = computed(() => {
    if (!eventDate.value) return null;
    return new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(eventDate.value);
});

const utcTimeFormatted = computed(() => {
    if (!eventDate.value) return null;
    return new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        timeZone: "UTC",
        timeZoneName: "short",
    }).format(eventDate.value);
});

const estTimeFormatted = computed(() => {
    if (!eventDate.value) return null;
    return new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        timeZone: "America/New_York",
        timeZoneName: "short",
    }).format(eventDate.value);
});

const countdown = ref("");
let countdownInterval: ReturnType<typeof setInterval> | null = null;

function updateCountdown() {
    if (!eventDate.value) return;
    const now = Date.now();
    const target = eventDate.value.getTime();
    const diff = target - now;

    if (diff <= 0) {
        countdown.value = "Event started";
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        return;
    }

    const totalSeconds = Math.floor(diff / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);

    if (days > 0) {
        countdown.value = `in ${days}d ${hours}h ${minutes}m`;
    } else {
        countdown.value = `in ${hours}h ${minutes}m`;
    }
}

onMounted(() => {
    if (eventDate.value) {
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 60000);
    }
});

onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});
</script>

<template>
    <Alert :id="`banner-${props.banner.id}`" :type="props.banner.type">
        <img
            v-if="props.banner.bannerImage"
            :src="props.banner.bannerImage"
            alt="Event banner"
            class="max-h-[120px] w-full rounded object-cover"
        />

        <VueMarkdown
            :source="props.banner.message"
            class="prose prose-sm prose-stone prose-invert prose-headings:mb-2 prose-headings:mt-0 prose-headings:font-semibold"
        ></VueMarkdown>

        <div
            v-if="hasMetadata"
            class="flex items-center gap-2 text-sm text-stone-300"
        >
            <template v-if="eventDate">
                <Tooltip>
                    <span>{{ localTimeFormatted }} (your time)</span>
                    <template #popper>
                        <div>{{ utcTimeFormatted }}</div>
                        <div>{{ estTimeFormatted }}</div>
                    </template>
                </Tooltip>
                <span>&middot;</span>
                <span>{{ countdown }}</span>
            </template>
            <template v-if="eventDate && banner.detailsUrl">
                <span>&middot;</span>
            </template>
            <a
                v-if="banner.detailsUrl"
                :href="banner.detailsUrl"
                target="_blank"
                rel="noopener"
                class="underline hover:text-white"
                >Event Details</a
            >
        </div>
    </Alert>
</template>

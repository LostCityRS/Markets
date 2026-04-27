<script setup lang="ts">
const page = usePage();

const currentTab = computed(() => {
    if (page.url.startsWith("/stats")) return "stats";

    const url = new URL(page.url, "http://placeholder");
    const tab = url.searchParams.get("tab") ?? "buy";

    return tab;
});

// momentum-trail's route('home', ...) returns an empty/relative string for
// the root-path route, so we build the home tab hrefs explicitly.
const tabs = computed(() => [
    {
        key: "buy",
        label: "Buy Listings",
        mobileLabel: "Buying",
        href: "/?tab=buy",
    },
    {
        key: "sell",
        label: "Sell Listings",
        mobileLabel: "Selling",
        href: "/?tab=sell",
    },
    {
        key: "favorites",
        label: "Favorites",
        mobileLabel: "Favorites",
        href: "/?tab=favorites",
    },
    {
        key: "stats",
        label: "Stats",
        mobileLabel: "Stats",
        href: route("stats.index"),
    },
]);
</script>

<template>
    <div class="relative z-10 mb-[-2px] flex flex-row">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            preserve-scroll
            class="px-4 py-3 font-bold"
            :class="{
                'border-2 border-b-0 border-[#382418] bg-black':
                    currentTab === tab.key,
            }"
        >
            <span class="sm:hidden">{{ tab.mobileLabel }}</span>

            <span class="hidden sm:inline">{{ tab.label }}</span>
        </Link>
    </div>
</template>

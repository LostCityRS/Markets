<script setup lang="ts">
const props = defineProps<Pages.StatsPage>();
</script>

<template>
    <LayoutMain>
        <ItemSearch />

        <MarketTabs />

        <div class="border-2 border-[#382418] bg-black p-4">
            <div
                v-if="props.featuredItem"
                class="mb-6 flex flex-col gap-3 border-2 border-[#382418] bg-stone-950 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2 class="text-lg font-bold text-[#90c040]">Featured Item</h2>

                <Link
                    :href="
                        route('items.show', {
                            item: props.featuredItem.item.slug,
                        })
                    "
                    class="flex items-center gap-3 hover:underline"
                >
                    <ItemImage
                        :item="props.featuredItem.item"
                        :quantity="props.featuredItem.quantity"
                    />

                    <div class="flex flex-col">
                        <p class="font-bold">
                            {{ props.featuredItem.item.name }}
                        </p>

                        <p class="text-stone-300">
                            {{ formatGold(props.featuredItem.total) }} gp
                        </p>
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="mb-6 border-2 border-[#382418] bg-stone-950 p-4 text-stone-400"
            >
                No featured item today.
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatTable
                    title="Top Volume (24h)"
                    :rows="props.topVolume"
                    unit="gp"
                />

                <StatTable
                    title="Most Expensive Items"
                    :rows="props.topExpensiveItems"
                    unit="gp"
                />

                <StatTable
                    title="Most Traded"
                    :rows="props.topTraded"
                    unit="trades"
                />

                <StatTable
                    title="Biggest Trades (24 h)"
                    :rows="props.topExpensiveTrades"
                    unit="gp"
                    trade-mode
                />
            </div>
        </div>
    </LayoutMain>
</template>

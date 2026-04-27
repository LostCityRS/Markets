<script setup lang="ts">
type StatItem = Data.Stats.StatItemData;
type StatTrade = Data.Stats.StatTradeData;

const props = defineProps<{
    title: string;
    rows: StatItem[] | StatTrade[];
    unit: "gp" | "trades";
    tradeMode?: boolean;
}>();

const isTrade = (row: StatItem | StatTrade): row is StatTrade =>
    props.tradeMode === true;

const valueLabel = (row: StatItem | StatTrade): string => {
    if (isTrade(row)) {
        return `${formatGold(row.total)} gp`;
    }

    if (props.unit === "gp") {
        return `${formatGold(row.value)} gp`;
    }

    return `${row.value.toLocaleString()} ${props.unit}`;
};
</script>

<template>
    <div class="flex flex-col border-2 border-[#382418] bg-stone-950">
        <div class="border-b-2 border-[#382418] bg-stone-900 px-3 py-2">
            <h3 class="font-bold text-[#90c040]">{{ title }}</h3>
        </div>

        <div v-if="!rows.length" class="px-3 py-4 text-stone-400">
            No data yet.
        </div>

        <ol v-else class="flex flex-col">
            <li
                v-for="(row, index) in rows"
                :key="index"
                class="flex items-center gap-3 border-b border-[#382418]/50 px-3 py-2 last:border-b-0"
            >
                <span class="w-6 shrink-0 text-right font-bold text-stone-400">
                    {{ index + 1 }}.
                </span>

                <Link
                    :href="route('items.show', { item: row.item.slug })"
                    class="flex min-w-0 flex-1 items-center gap-2 hover:underline"
                >
                    <ItemImage
                        :item="row.item"
                        :quantity="isTrade(row) ? row.quantity : undefined"
                    />

                    <span class="truncate font-bold">
                        {{ row.item.name }}
                    </span>
                </Link>

                <span class="shrink-0 text-right text-[#90c040]">
                    {{ valueLabel(row) }}
                </span>
            </li>
        </ol>
    </div>
</template>

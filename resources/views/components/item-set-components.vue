<script setup lang="ts">
import { Link } from "@inertiajs/vue3";

defineProps<{
    item: Data.Item.ItemData;
}>();
</script>

<template>
    <section
        v-if="item.isSet && item.setComponents?.length"
        class="border-2 border-stone-700 bg-stone-900 p-3"
    >
        <h2 class="mb-2 text-lg font-bold">Set contents</h2>

        <ul class="flex flex-col gap-2">
            <li
                v-for="c in item.setComponents"
                :key="c.item.id"
                class="flex flex-row items-center gap-3"
            >
                <Link
                    :href="route('items.show', { item: c.item })"
                    class="flex flex-row items-center gap-3 hover:underline"
                >
                    <div class="size-fit border-2 border-stone-600 bg-stone-800 p-1">
                        <img
                            :src="`/img/items/${encodeURIComponent(c.item.slug)}.webp`"
                            :alt="`${c.item.name} icon`"
                            width="32"
                            height="32"
                        />
                    </div>

                    <span class="text-[#90C040]">
                        {{ c.quantity > 1 ? `${c.quantity} × ` : "" }}{{ c.item.name }}
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>

<script setup lang="ts">
import { Tooltip } from "floating-vue";
import "floating-vue/dist/style.css";
import MkBump from "@/views/components/MkBump.vue";
import MkPause from "@/views/components/MkPause.vue";
import MkPlay from "@/views/components/MkPlay.vue";
import MkCheck from "@/views/components/MkCheck.vue";
import MkEdit from "@/views/components/MkEdit.vue";
import MkRemove from "@/views/components/MkRemove.vue";

const props = defineProps<{
    listing: Data.Listing.ListingData;
}>();

const typeVerb = computed(() => {
    return props.listing.type === "buy" ? "Bought" : "Sold";
});

const page = usePage();
const currentUrl = computed(() => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.delete("redirect");
    return url.pathname + url.search + url.hash;
});
</script>

<template>
    <td v-if="listing.canManage && !listing.soldAt" class="sm:px-1">
        <div class="flex flex-nowrap justify-end gap-1">
            <DropdownMenu>
                <DropdownItem
                    :icon="MkBump"
                    text-color="text-amber-400"
                    @click="
                        router.patch(
                            route('listing.bump', {
                                listing: listing,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    Bump
                </DropdownItem>

                <DropdownItem
                    v-if="!listing.pausedAt"
                    :icon="MkPause"
                    text-color="text-rose-500"
                    @click="
                        router.post(
                            route('listing.pause.store', {
                                listing: listing,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    Pause
                </DropdownItem>

                <DropdownItem
                    v-else
                    :icon="MkPlay"
                    text-color="text-emerald-500"
                    @click="
                        router.delete(
                            route('listing.pause.destroy', {
                                listing: listing,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    Unpause
                </DropdownItem>

                <DropdownItem
                    :icon="MkCheck"
                    text-color="text-green-500"
                    @click="
                        router.visit(
                            route('listing.sale.store', {
                                listing,
                                redirect: currentUrl,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    Mark {{ typeVerb }}
                </DropdownItem>

                <DropdownItem
                    :icon="MkEdit"
                    text-color="text-amber-500"
                    @click="
                        router.visit(route('listings.edit', { listing }), {
                            preserveScroll: true,
                        })
                    "
                >
                    Edit
                </DropdownItem>

                <DropdownItem
                    :icon="MkRemove"
                    text-color="text-red-500"
                    @click="
                        router.visit(
                            route('listings.delete', {
                                listing,
                                redirect: currentUrl,
                            }),
                            { preserveScroll: true },
                        )
                    "
                >
                    Remove
                </DropdownItem>
            </DropdownMenu>
        </div>
    </td>
</template>

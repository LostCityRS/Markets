<script setup lang="ts">
import { Tooltip } from "floating-vue";
import "floating-vue/dist/style.css";
import { pickBy } from "lodash";
import LayoutUser from "@/views/layouts/admin/layout-user.vue";
import MkCheck from "@/views/components/MkCheck.vue";
import MkRemove from "@/views/components/MkRemove.vue";
import MkPause from "@/views/components/MkPause.vue";
import MkPlay from "@/views/components/MkPlay.vue";
import MkEdit from "@/views/components/MkEdit.vue";

const props = defineProps<Pages.Admin.UserListingsIndexPage>();

const form = ref({
    item: props.filters.item || "",
});

const submitFilter = () => {
    router.get(
        route("admin.users.listings.index", { user: props.selected_user }),
        pickBy(form.value),
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

const page = usePage();
const currentUrl = computed(() => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.delete("redirect");
    return url.pathname + url.search + url.hash;
});
</script>

<template>
    <LayoutUser :selected-user="props.selected_user">
        <Head :title="`${props.selected_user.name} Listings`" />

        <ListingTable
            class="relative overflow-x-auto"
            table-class="border-separate border-spacing-y-2 -mt-2"
        >
            <template #header>
                <div class="align-center flex gap-3">
                    <span class="font-bold text-stone-300">Filters:</span>

                    <input
                        id="item"
                        v-model="form.item"
                        name="item"
                        type="text"
                        class="border-slate-900 bg-stone-700 py-0 pl-1"
                        placeholder="Search by item..."
                        @keydown.enter="submitFilter"
                    />

                    <BaseButton
                        variant="danger"
                        :href="
                            route('admin.users.listings.index', {
                                user: props.selected_user,
                                reset: 1,
                            })
                        "
                        as="link"
                    >
                        <MkRemove class="size-4" />
                    </BaseButton>
                </div>
            </template>

            <template #table-header>
                <tr class="bg-stone-950 text-left text-stone-300">
                    <th>
                        <SortHeader
                            :current-sort="props.filters.sort || '-updated_at'"
                            field="id"
                            default-sort="-id"
                            class="py-2 font-semibold"
                            >ID
                        </SortHeader>
                    </th>

                    <th></th>

                    <th>
                        <SortHeader
                            :current-sort="props.filters.sort || '-updated_at'"
                            field="item"
                            class="py-2 font-semibold"
                            >Item
                        </SortHeader>
                    </th>

                    <th></th>

                    <th colspan="2">Notes</th>

                    <th>
                        <SortHeader
                            :current-sort="props.filters.sort || '-updated_at'"
                            field="created_at"
                            class="py-2 font-semibold"
                            >Created
                        </SortHeader>
                    </th>

                    <th>
                        <SortHeader
                            :current-sort="props.filters.sort || '-updated_at'"
                            field="updated_at"
                            class="py-2 font-semibold"
                        >
                            Updated
                        </SortHeader>
                    </th>

                    <th></th>
                </tr>
            </template>

            <tr v-for="listing in props.listings.data" :key="listing.id">
                <td>#{{ listing.id }}</td>

                <td>
                    <Tooltip>
                        <MkCheck
                            v-if="listing.status === 'Sold'"
                            class="size-6"
                        />

                        <template #popper> Sold </template>
                    </Tooltip>

                    <Tooltip>
                        <MkRemove
                            v-if="listing.status === 'Deleted'"
                            class="size-6"
                        />

                        <template #popper> Deleted </template>
                    </Tooltip>

                    <Tooltip>
                        <MkPause
                            v-if="listing.status === 'Paused'"
                            class="size-6"
                        />

                        <template #popper> Paused </template>
                    </Tooltip>

                    <Tooltip>
                        <MkClock
                            v-if="listing.status === 'Expiring'"
                            class="size-6"
                        />

                        <template #popper> Expiring </template>
                    </Tooltip>

                    <Tooltip>
                        <MkExpired
                            v-if="listing.status === 'Expired'"
                            class="size-6 text-stone-400"
                        />

                        <template #popper> Expired </template>
                    </Tooltip>
                </td>

                <ItemTableData :item="listing.item"></ItemTableData>

                <PriceTableData :listing="listing" />

                <NoteTableData :listing="listing" />

                <td>
                    <Tooltip>
                        <span>{{
                            new Date(listing.createdAt).toLocaleDateString()
                        }}</span>

                        <template #popper>
                            {{ new Date(listing.createdAt).toLocaleString() }}
                        </template>
                    </Tooltip>
                </td>

                <td>
                    <Tooltip>
                        <span>{{
                            new Date(listing.updatedAt).toLocaleDateString()
                        }}</span>

                        <template #popper>
                            {{ new Date(listing.updatedAt).toLocaleString() }}
                        </template>
                    </Tooltip>
                </td>

                <td class="sm:px-1">
                    <div class="flex flex-nowrap justify-end gap-1">
                        <DropdownMenu>
                            <DropdownItem
                                v-if="
                                    (listing.status === 'Active' ||
                                        listing.status === 'Expiring') &&
                                    !listing.pausedAt
                                "
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
                                v-if="
                                    listing.status === 'Paused' &&
                                    listing.pausedAt
                                "
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
                                v-if="
                                    listing.status === 'Active' ||
                                    listing.status === 'Expiring'
                                "
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
                                Mark
                                {{ listing.type === "buy" ? "Bought" : "Sold" }}
                            </DropdownItem>

                            <DropdownItem
                                :icon="MkEdit"
                                text-color="text-amber-500"
                                @click="
                                    router.visit(
                                        route('listings.edit', { listing }),
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                "
                            >
                                Edit
                            </DropdownItem>

                            <DropdownItem
                                v-if="listing.status !== 'Deleted'"
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
            </tr>

            <tr v-if="!props.listings.data.length">
                <td class="text-center" colspan="5">No listings found.</td>
            </tr>

            <template v-if="props.listings.data.length" #footer>
                <Pagination :data="props.listings" />
            </template>
        </ListingTable>
    </LayoutUser>
</template>

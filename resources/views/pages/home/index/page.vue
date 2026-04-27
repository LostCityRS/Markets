<script setup lang="ts">
import {
    ArrowLeftEndOnRectangleIcon,
    BookmarkSlashIcon,
    Cog6ToothIcon,
} from "@heroicons/vue/24/outline";
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionChild,
    TransitionRoot,
} from "@headlessui/vue";

const props = defineProps<Pages.HomeIndexPage>();
const auth = useAuth();

const showBanNotice = ref(auth.value?.is_banned ?? false);

const dismissBanNotice = () => {
    showBanNotice.value = false;
};

const listings = ref<Data.Listing.ListingData[]>([...props.listings.data]);

watch(
    () => props.listings.data,
    (newListings) => {
        listings.value = [...newListings];
    },
);

const favoritesListingTypes = computed((): Enums.FavoritesListingType[] => [
    "all",
    "buy",
    "sell",
]);

const highlightedIds = ref<number[]>([]);

// Subscribe to the "listings" channel using Laravel Echo, filtering by the current item's id.
onMounted(() => {
    if (!window.Echo) {
        return;
    }

    const channel = window.Echo.channel("listings");

    channel.listen("ListingEvent", (listingEvent: Data.Listing.ListingData) => {
        if (auth) {
            listingEvent.canManage =
                props.usernames?.includes(listingEvent.username) || false;
        }

        // If the listing event is not for the current item or the listing type doesn't match, ignore it.
        if (
            !listingEvent.item ||
            ((props.tab === "buy" || props.tab === "sell") &&
                listingEvent.type !== props.tab) ||
            (props.tab === "favorites" &&
                (!props.favorites
                    ?.map((f) => f.id)
                    .includes(listingEvent.item.id) ||
                    (props.listingType !== "all" &&
                        listingEvent.type !== props.listingType)))
        ) {
            return;
        }

        // If the listingEvent has a sold, deleted, or is paused timestamp, remove the listing from the list if it exists.
        if (
            listingEvent.soldAt ||
            listingEvent.deletedAt ||
            listingEvent.pausedAt
        ) {
            const index = listings.value.findIndex(
                (l) => l.id === listingEvent.id,
            );

            if (index !== -1) {
                listings.value.splice(index, 1);
            }
        } else {
            // Check if the listing already exists in the list.
            const index = listings.value.findIndex(
                (l) => l.id === listingEvent.id,
            );

            if (index === -1) {
                // If it doesn't exist, add it to the front of the list.
                const usernameCount = listings.value
                    .slice(0, 20)
                    .filter((l) => l.username === listingEvent.username).length;
                if (usernameCount < 3) {
                    listings.value.unshift(listingEvent);
                    // Add the id to the highlightedIds to highlight the new listing.
                    highlightedIds.value.push(listingEvent.id);
                }

                // If the list is too long, remove the last listing.
                if (listings.value.length > 30) {
                    listings.value.pop();
                }
            } else {
                // If it exists, update the listing.
                const existingListing = listings.value[index];
                listings.value[index] = listingEvent;

                // If the updatedAt timestamp changed, move it to the front of the list.
                if (existingListing.updatedAt !== listingEvent.updatedAt) {
                    listings.value.splice(index, 1);
                    listings.value.unshift(listingEvent);
                    // Add the id to the highlightedIds to highlight the updated listing.
                    highlightedIds.value.push(listingEvent.id);
                }
            }
        }
    });
});

// Cleanup the subscription when the component is unmounted.
onUnmounted(() => {
    if (!window.Echo) {
        return;
    }

    window.Echo.leaveChannel("listings");
});
</script>

<template>
    <LayoutMain>
        <TransitionRoot appear :show="showBanNotice" as="template">
            <Dialog as="div" class="relative z-50" @close="dismissBanNotice">
                <TransitionChild
                    as="template"
                    enter="ease-out duration-200"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="ease-in duration-150"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-stone-950/70" />
                </TransitionChild>

                <div class="fixed inset-0 overflow-y-auto">
                    <div
                        class="flex min-h-full items-center justify-center p-4"
                    >
                        <TransitionChild
                            as="template"
                            enter="ease-out duration-200"
                            enter-from="opacity-0 scale-95"
                            enter-to="opacity-100 scale-100"
                            leave="ease-in duration-150"
                            leave-from="opacity-100 scale-100"
                            leave-to="opacity-0 scale-95"
                        >
                            <DialogPanel
                                class="flex w-full max-w-lg flex-col gap-4 border-2 border-red-900 bg-stone-900 p-5 text-left"
                            >
                                <DialogTitle
                                    class="text-xl font-bold text-red-500"
                                >
                                    You're banned.
                                </DialogTitle>

                                <div
                                    v-if="auth?.banned_reason"
                                    class="border-l-4 border-red-800 bg-stone-950/60 p-3"
                                >
                                    <p
                                        class="text-sm font-semibold text-stone-400"
                                    >
                                        Reason
                                    </p>

                                    <p
                                        class="whitespace-pre-wrap text-stone-200"
                                    >
                                        {{ auth.banned_reason }}
                                    </p>
                                </div>

                                <p class="text-stone-300">
                                    Message
                                    <code
                                        class="rounded bg-stone-800 px-1.5 py-0.5 text-stone-100"
                                        >redbracket</code
                                    >
                                    on Discord to appeal.
                                </p>

                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        class="rounded-sm bg-stone-800 px-4 py-2 text-stone-100 hover:bg-stone-700"
                                        @click="dismissBanNotice"
                                    >
                                        Dismiss
                                    </button>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>

        <ItemSearch />

        <MarketTabs />

        <ListingTable @mouseenter="highlightedIds = []">
            <template
                v-if="
                    auth &&
                    props.tab === 'favorites' &&
                    props.favorites &&
                    props.favorites.length > 0
                "
                #header
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex gap-2">
                        <BaseButton
                            as="link"
                            variant="secondary"
                            class="!px-3"
                            :href="
                                route('home', {
                                    type: favoritesListingTypes[0],
                                })
                            "
                            :force-focus="
                                props.listingType === favoritesListingTypes[0]
                            "
                        >
                            All
                        </BaseButton>

                        <BaseButton
                            as="link"
                            variant="secondary"
                            :href="
                                route('home', {
                                    type: favoritesListingTypes[1],
                                })
                            "
                            :force-focus="
                                props.listingType === favoritesListingTypes[1]
                            "
                        >
                            Buy
                        </BaseButton>

                        <BaseButton
                            as="link"
                            variant="secondary"
                            :href="
                                route('home', {
                                    type: favoritesListingTypes[2],
                                })
                            "
                            :force-focus="
                                props.listingType === favoritesListingTypes[2]
                            "
                        >
                            Sell
                        </BaseButton>
                    </div>

                    <Link
                        :href="route('favorites.index')"
                        preserve-scroll
                        class="flex items-center gap-1 text-[#90c040] hover:underline"
                    >
                        <Cog6ToothIcon class="size-5" />

                        <span class="hidden sm:inline">Manage</span>

                        Favorites
                    </Link>
                </div>
            </template>

            <td
                v-if="!auth && props.tab === 'favorites'"
                class="flex flex-col items-center justify-center gap-2 px-2 py-6 sm:flex-row"
            >
                <ArrowLeftEndOnRectangleIcon class="size-12 text-stone-500" />

                <div class="text-center sm:text-left">
                    <p class="text-lg font-semibold">
                        You must be signed in to use this feature.
                    </p>

                    <Link
                        :href="route('login')"
                        class="text-[#90c040] hover:underline"
                    >
                        Sign in here
                    </Link>
                </div>
            </td>

            <td
                v-else-if="
                    auth &&
                    props.tab === 'favorites' &&
                    (props.favorites === null || !props.favorites.length)
                "
                class="flex flex-col items-center justify-center gap-2 px-2 py-6 sm:flex-row"
            >
                <BookmarkSlashIcon class="size-12 text-stone-500" />

                <div class="text-center sm:text-left">
                    <p class="text-lg font-semibold">
                        You have no favorite items.
                    </p>

                    <p class="text-stone-300">
                        Add items to your favorites by clicking the bookmark
                        icon on a listing.
                    </p>
                </div>
            </td>

            <EmptyTableRow v-else-if="!props.listings.data.length" />

            <ListingTableRow
                v-for="l in listings.slice(0, 20)"
                :key="l.id"
                :listing="l"
                :highlighted="highlightedIds.includes(l.id)"
            >
                <template #default="{ listing }">
                    <ItemTableData :item="listing.item" />

                    <PriceTableData :listing="listing" />

                    <UsernameTableData :listing="listing" />

                    <TimestampTableData
                        :timestamp="listing.updatedAt"
                        :use-color="props.tab === 'favorites'"
                    />

                    <NoteTableData :listing="listing" />

                    <ActionTableData :listing="listing" />
                </template>
            </ListingTableRow>

            <template v-if="props.listings.data.length" #footer>
                <Pagination :data="props.listings" />
            </template>
        </ListingTable>
    </LayoutMain>
</template>

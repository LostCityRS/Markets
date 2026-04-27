<script setup lang="ts">
const props = defineProps<Pages.RecentSalesPage>();
</script>

<template>
    <LayoutMain>
        <ItemSearch />

        <MarketTabs />

        <ListingTable>
            <EmptyTableRow v-if="!props.listings.data.length" />

            <ListingTableRow
                v-for="l in props.listings.data"
                :key="l.id"
                :listing="l"
            >
                <template #default="{ listing }">
                    <ItemTableData :item="listing.item" />

                    <PriceTableData :listing="listing" />

                    <UsernameTableData :listing="listing" />

                    <TimestampTableData
                        :timestamp="listing.soldAt || ''"
                        :use-color="false"
                    />

                    <NoteTableData :listing="listing" />
                </template>
            </ListingTableRow>

            <template v-if="props.listings.data.length" #footer>
                <Pagination :data="props.listings" />
            </template>
        </ListingTable>
    </LayoutMain>
</template>

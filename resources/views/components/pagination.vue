<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import { usePaginator } from "momentum-paginator";

const props = defineProps<{
    data: Paginator<any>;
}>();
const { first, last, previous, next, pages } = usePaginator(props.data);

const meta = computed(() => (props.data as any).meta);
const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);

const jumpPage = ref<number | string>(currentPage.value);
watch(currentPage, (n) => {
    jumpPage.value = n;
});

// Find which query param this paginator uses (`page`, `sold_page`, etc.)
// by matching a page link's URL param value against its label.
function getPageParamName(): string {
    const known = pages.find(
        (p) => p.url && p.label && !Number.isNaN(Number(p.label)),
    );
    if (!known?.url) return "page";
    const url = new URL(known.url, window.location.origin);
    for (const [key, value] of url.searchParams.entries()) {
        if (value === String(known.label)) return key;
    }
    return "page";
}

// Merge current query params (filters/sort) into the page url so they persist
// across navigation. Skip keys the pageUrl already sets — those are the
// page-param for this paginator and must win. This lets multiple paginators
// coexist (e.g. `page` + `sold_page`).
function buildUrl(pageUrl?: string | null): string {
    if (!pageUrl) return "#";
    const urlObj = new URL(pageUrl, window.location.origin);
    const pageParams = new URLSearchParams(urlObj.search);
    const currentParams = new URLSearchParams(window.location.search);
    for (const [key, value] of currentParams.entries()) {
        if (pageParams.has(key)) continue;
        pageParams.set(key, value);
    }
    const qs = pageParams.toString();
    return urlObj.pathname + (qs ? `?${qs}` : "");
}

function jumpTo() {
    const n = Number(jumpPage.value);
    if (!Number.isFinite(n) || n < 1 || n > lastPage.value) {
        jumpPage.value = currentPage.value;
        return;
    }
    if (n === currentPage.value) return;
    const paramName = getPageParamName();
    const reference = pages.find((p) => p.url)?.url;
    if (!reference) return;
    const urlObj = new URL(reference, window.location.origin);
    urlObj.searchParams.set(paramName, String(n));
    router.visit(buildUrl(urlObj.pathname + urlObj.search), {
        preserveScroll: true,
    });
}

function refresh() {
    router.reload({ preserveScroll: true });
}
</script>

<template>
    <nav
        v-if="props.data.data.length"
        class="-mx-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 text-sm sm:mx-0 sm:px-0"
    >
        <ul class="flex flex-row flex-wrap items-center gap-x-3">
            <li>
                <Link
                    v-if="first.isActive"
                    :href="buildUrl(first.url)"
                    class="text-[#90C040] hover:underline"
                >
                    &lt;&lt; first
                </Link>

                <span v-else class="text-stone-600">&lt;&lt; first</span>
            </li>

            <li>
                <Link
                    v-if="previous.isActive"
                    :href="buildUrl(previous.url)"
                    class="text-[#90C040] hover:underline"
                >
                    &lt; prev
                </Link>

                <span v-else class="text-stone-600">&lt; prev</span>
            </li>

            <li class="inline-flex items-center gap-x-1.5">
                <span>Page</span>

                <input
                    v-model="jumpPage"
                    type="text"
                    size="2"
                    maxlength="3"
                    inputmode="numeric"
                    class="w-10 border border-stone-700 bg-stone-900 px-1 py-0.5 text-center text-white focus:border-[#90C040] focus:outline-none"
                    @keydown.enter="jumpTo"
                    @blur="jumpTo"
                />

                <span>of {{ lastPage }}</span>
            </li>

            <li>
                <Link
                    v-if="next.isActive"
                    :href="buildUrl(next.url)"
                    class="text-[#90C040] hover:underline"
                >
                    next &gt;
                </Link>

                <span v-else class="text-stone-600">next &gt;</span>
            </li>

            <li>
                <Link
                    v-if="last.isActive"
                    :href="buildUrl(last.url)"
                    class="text-[#90C040] hover:underline"
                >
                    last &gt;&gt;
                </Link>

                <span v-else class="text-stone-600">last &gt;&gt;</span>
            </li>
        </ul>

        <ul class="flex flex-row items-center gap-x-3">
            <li>
                <button
                    type="button"
                    class="inline-flex items-center gap-x-1 text-[#90C040] hover:underline"
                    @click="refresh"
                >
                    <img
                        src="/img/forum/refresh.gif"
                        alt=""
                        class="inline-block"
                    />
                    Refresh
                </button>
            </li>
        </ul>
    </nav>
</template>

<script setup lang="ts">
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import VueMarkdown from "vue-markdown-render";
const props = defineProps<Pages.Admin.BannersCreatePage>();

const form = useForm({
    ...props.bannerForm,
});

const bannerTypes = computed((): Enums.BannerType[] => [
    "default",
    "info",
    "warning",
    "error",
    "success",
]);

const display_scopes = computed((): Enums.BannerDisplayScope[] => [
    "global",
    "item",
]);

const timezones = [
    { label: "UTC", value: "UTC" },
    { label: "Eastern", value: "America/New_York" },
    { label: "Pacific", value: "America/Los_Angeles" },
] as const;

// Initialize timezone from form data (persisted) or default to UTC
const initialTz =
    timezones.find((tz) => tz.value === props.bannerForm.timezone) ??
    timezones[0];
form.timezone = initialTz.value;

const submitRoute = props.bannerForm.id
    ? route("admin.banners.update", { id: props.bannerForm.id })
    : route("admin.banners.store");

const submitMethod = props.bannerForm.id ? "put" : "post";
const pageTitle = computed(() => `${form.id ? "Edit" : "Create"} Banner`);

const submit = () => {
    form[submitMethod ?? "post"](submitRoute, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const selectedItem = ref<Data.Item.ItemData | null>(null);
const showPreview = ref(false);

// Watch for selectedItem change, add it to the form
watch(selectedItem, (newItem) => {
    if (newItem) {
        addItemToForm(newItem);
        selectedItem.value = null;
    }
});

// Computed end_at display (always event_at + 1h, read-only)
const endAtDisplay = computed(() => {
    if (!form.event_at) return null;
    const eventDate = new Date(form.event_at);
    eventDate.setHours(eventDate.getHours() + 1);
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${eventDate.getFullYear()}-${pad(eventDate.getMonth() + 1)}-${pad(eventDate.getDate())}T${pad(eventDate.getHours())}:${pad(eventDate.getMinutes())}`;
});

const addItemToForm = (item: Data.Item.ItemFormData) => {
    if (!form.items) {
        form.items = [];
    }

    if (!form.items.some((i: Data.Item.ItemFormData) => i.id === item.id)) {
        form.items.push(item);
    }
};

const removeItemFromForm = (item: Data.Item.ItemFormData) => {
    if (form.items) {
        form.items = form.items.filter(
            (i: Data.Item.ItemFormData) => i.id !== item.id,
        );
    }
};
</script>

<template>
    <LayoutAdmin>
        <Head :title="pageTitle" />

        <div class="mx-auto flex max-w-[620px] flex-col gap-4">
            <div class="flex items-center gap-2">
                <BaseButton
                    variant="secondary"
                    as="link"
                    :href="route('admin.banners.index')"
                    class="flex items-center gap-1"
                >
                    <ArrowLeftIcon class="size-5" />
                    Back
                </BaseButton>

                <h2 class="text-2xl font-bold">
                    {{ pageTitle }}
                </h2>
            </div>

            <form
                class="flex flex-col gap-4 border-2 border-[#382418] bg-black p-3"
                @submit.prevent="submit"
            >
                <div
                    v-if="Object.keys(form.errors).length !== 0"
                    class="text-red-500"
                >
                    <p v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="cursor-pointer"
                    />

                    <label for="is_active" class="cursor-pointer">
                        Banner is Active
                    </label>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="message">Message</label>

                    <textarea
                        id="message"
                        v-model="form.message"
                        class="border-slate-900 bg-stone-700 p-2"
                        placeholder="Enter your message here..."
                        rows="4"
                    ></textarea>
                </div>

                <div class="flex flex-col gap-2">
                    <button
                        type="button"
                        class="w-fit italic text-stone-400 hover:text-stone-300"
                        @click="showPreview = !showPreview"
                    >
                        Toggle Preview
                    </button>

                    <Alert v-if="showPreview" :type="form.type">
                        <VueMarkdown
                            v-if="form.message"
                            :source="form.message"
                            class="prose prose-sm prose-stone prose-invert prose-headings:mb-2 prose-headings:mt-0 prose-headings:font-semibold"
                        ></VueMarkdown>
                    </Alert>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex w-full flex-col gap-2 sm:w-1/3">
                        <label>Display Scope</label>

                        <div
                            v-for="scope in display_scopes"
                            :key="scope"
                            class="flex items-center gap-2"
                        >
                            <input
                                :id="'display_scope_' + scope"
                                v-model="form.display_scope"
                                type="radio"
                                :value="scope"
                                class="cursor-pointer"
                            />

                            <label
                                :for="'display_scope_' + scope"
                                class="cursor-pointer"
                            >
                                {{
                                    scope.charAt(0).toUpperCase() +
                                    scope.slice(1)
                                }}
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="form.display_scope === 'item'"
                        class="flex w-full flex-col gap-2 sm:w-2/3"
                    >
                        <label for="item">Show banner for these items:</label>

                        <ItemSelect id="item" v-model="selectedItem" />

                        <span v-if="!form.items || form.items.length === 0">
                            No items selected.
                        </span>

                        <table
                            v-if="form.items && form.items.length > 0"
                            class="table-auto border-collapse"
                        >
                            <tbody>
                                <tr v-for="item in form.items" :key="item.id">
                                    <ItemTableData :item="item"></ItemTableData>

                                    <td>
                                        {{ item.name }}
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="text-red-500 hover:underline"
                                            @click="removeItemFromForm(item)"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="my-3 border-stone-600" />

                <div class="flex items-center gap-2">
                    <span class="text-sm text-stone-400">Timezone:</span>
                    <button
                        v-for="tz in timezones"
                        :key="tz.value"
                        type="button"
                        class="rounded px-2 py-0.5 text-xs"
                        :class="
                            form.timezone === tz.value
                                ? 'bg-stone-600 text-white'
                                : 'bg-stone-800 text-stone-400 hover:text-stone-300'
                        "
                        @click="form.timezone = tz.value"
                    >
                        {{ tz.label }}
                    </button>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="type">Style</label>

                    <select
                        id="type"
                        v-model="form.type"
                        class="border-slate-900 bg-stone-700 p-2"
                    >
                        <option
                            v-for="type in bannerTypes"
                            :key="type"
                            :value="type"
                        >
                            {{
                                type.charAt(0).toUpperCase() + type.slice(1)
                            }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex w-full flex-col gap-2 sm:w-1/2">
                        <label for="event_at">Event Time</label>

                        <input
                            id="event_at"
                            v-model="form.event_at"
                            type="datetime-local"
                            class="w-full border-slate-900 bg-stone-700 p-2"
                        />
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:w-1/2">
                        <label for="end_at">End At</label>

                        <input
                            id="end_at"
                            :value="endAtDisplay"
                            type="datetime-local"
                            class="w-full border-slate-900 bg-stone-700/50 p-2 text-stone-400"
                            disabled
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex w-full grow flex-col gap-2 sm:w-1/2">
                        <label for="details_url">Details URL (optional)</label>

                        <input
                            id="details_url"
                            v-model="form.details_url"
                            type="text"
                            placeholder="https://..."
                            class="w-full border-slate-900 bg-stone-700 p-2"
                        />
                    </div>
                </div>

                <BaseButton
                    type="submit"
                    variant="success"
                    class="mt-4 !px-6 !py-2"
                >
                    Submit
                </BaseButton>
            </form>
        </div>
    </LayoutAdmin>
</template>

<script setup lang="ts">
import VueMarkdown from "vue-markdown-render";
import { Cropper } from "vue-advanced-cropper";
import "vue-advanced-cropper/dist/style.css";
const props = defineProps<Pages.Admin.BannersCreatePage>();

const form = useForm({
    ...props.bannerForm,
    banner_image_file: null as File | null,
    remove_banner_image: false,
});

const bannerTypes = computed((): Enums.BannerType[] => ["default", "info"]);

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

const isEdit = !!props.bannerForm.id;
const submitRoute = isEdit
    ? route("admin.banners.update", { id: props.bannerForm.id })
    : route("admin.banners.store");

const pageTitle = computed(() => `${form.id ? "Edit" : "Create"} Banner`);

// Always POST with forceFormData — PHP cannot parse multipart/form-data bodies
// on PUT/PATCH, so use Laravel method spoofing via `_method` for updates.
const submit = () => {
    const target = isEdit
        ? form.transform((data) => ({ ...data, _method: "put" }))
        : form;
    target.post(submitRoute, {
        preserveScroll: true,
        forceFormData: true,
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

// Banner image upload + crop
const cropperSrc = ref<string | null>(null);
const cropperRef = ref<InstanceType<typeof Cropper> | null>(null);
const imagePreview = ref<string | null>(props.bannerForm.banner_image);
const fileInput = ref<HTMLInputElement | null>(null);

const triggerFileInput = () => {
    const el = document.querySelector<HTMLInputElement>('input[type="file"][accept="image/*"]');
    el?.click();
};

const onFileSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        cropperSrc.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
};

const applyCrop = () => {
    if (!cropperRef.value) return;

    const { canvas } = cropperRef.value.getResult();
    if (!canvas) return;

    canvas.toBlob(
        (blob: Blob | null) => {
            if (!blob) return;
            const file = new File([blob], "banner.jpg", {
                type: "image/jpeg",
            });
            form.banner_image_file = file;
            form.remove_banner_image = false;
            imagePreview.value = canvas.toDataURL("image/jpeg", 0.85);
            cropperSrc.value = null;
        },
        "image/jpeg",
        0.85,
    );
};

const cancelCrop = () => {
    cropperSrc.value = null;
    if (fileInput.value) fileInput.value.value = "";
};

const removeBannerImage = () => {
    form.banner_image_file = null;
    form.banner_image = null;
    form.remove_banner_image = true;
    imagePreview.value = null;
    cropperSrc.value = null;
    if (fileInput.value) fileInput.value.value = "";
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
                    <MkArrowLeft class="size-5" />
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

                <div class="flex flex-col gap-2">
                    <label>Banner Image (optional)</label>

                    <div
                        v-if="imagePreview && !cropperSrc"
                        class="flex flex-col gap-2"
                    >
                        <img
                            :src="imagePreview"
                            alt="Banner preview"
                            class="max-h-[120px] w-full rounded border border-stone-700 object-cover"
                        />
                        <div class="flex gap-2">
                            <label
                                for="banner_image_upload"
                                class="cursor-pointer text-sm text-stone-400 hover:text-stone-200"
                            >
                                Replace
                            </label>
                            <button
                                type="button"
                                class="text-sm text-red-400 hover:text-red-300"
                                @click="removeBannerImage"
                            >
                                Remove
                            </button>
                        </div>
                    </div>

                    <label
                        v-if="!imagePreview && !cropperSrc"
                        for="banner_image_upload"
                        class="flex cursor-pointer items-center gap-2 rounded border-2 border-dashed border-stone-600 p-4 text-stone-400 hover:border-stone-500 hover:text-stone-300"
                    >
                        <MkPhoto class="size-6" />
                        Upload banner image
                    </label>

                    <input
                        id="banner_image_upload"
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        @change="onFileSelected"
                    />

                    <div v-if="cropperSrc" class="flex flex-col gap-2">
                        <p class="text-sm text-stone-400">
                            Crop your image to banner proportions:
                        </p>
                        <Cropper
                            ref="cropperRef"
                            :src="cropperSrc"
                            :stencil-props="{
                                aspectRatio: 5,
                            }"
                            class="max-h-[300px] rounded border border-stone-700"
                        />
                        <div class="flex gap-2">
                            <BaseButton
                                type="button"
                                variant="success"
                                class="!px-4 !py-1 text-sm"
                                @click="applyCrop"
                            >
                                Apply Crop
                            </BaseButton>
                            <button
                                type="button"
                                class="text-sm text-stone-400 hover:text-stone-200"
                                @click="cancelCrop"
                            >
                                Cancel
                            </button>
                        </div>
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

<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{ modelValue: string | null; placeholder?: string }>(),
    { placeholder: 'Pick a date' },
);

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const open = ref(false);
const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const today = new Date();

function parseYmd(value: string | null): Date | null {
    if (!value) {
        return null;
    }

    const [y, m, d] = value.split('-').map(Number);

    return y && m && d ? new Date(y, m - 1, d) : null;
}

function toYmd(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const selected = computed(() => parseYmd(props.modelValue));
const view = ref<Date>(selected.value ? new Date(selected.value) : new Date());

// Keep the visible month in sync when the value changes externally.
watch(
    () => props.modelValue,
    (value) => {
        const date = parseYmd(value);

        if (date) {
            view.value = new Date(date);
        }
    },
);

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        month: 'long',
        year: 'numeric',
    }).format(view.value),
);

const triggerLabel = computed(() =>
    selected.value
        ? new Intl.DateTimeFormat(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(selected.value)
        : '',
);

// Month grid, Monday-first, with leading/trailing blanks.
const days = computed<(Date | null)[]>(() => {
    const year = view.value.getFullYear();
    const month = view.value.getMonth();
    const lead = (new Date(year, month, 1).getDay() + 6) % 7;
    const count = new Date(year, month + 1, 0).getDate();

    const cells: (Date | null)[] = Array.from({ length: lead }, () => null);

    for (let d = 1; d <= count; d++) {
        cells.push(new Date(year, month, d));
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    return cells;
});

function isSameDay(a: Date | null, b: Date | null): boolean {
    return (
        !!a &&
        !!b &&
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function shiftMonth(delta: number): void {
    view.value = new Date(
        view.value.getFullYear(),
        view.value.getMonth() + delta,
        1,
    );
}

function select(date: Date): void {
    emit('update:modelValue', toYmd(date));
    open.value = false;
}

function clear(): void {
    emit('update:modelValue', '');
}
</script>

<template>
    <PopoverRoot v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="flex h-9 w-40 items-center gap-2 rounded-md border border-input bg-background px-3 text-left text-sm transition-colors hover:bg-accent/50"
            >
                <CalendarDays class="size-4 shrink-0 opacity-70" />
                <span
                    class="truncate"
                    :class="selected ? '' : 'text-muted-foreground'"
                >
                    {{ triggerLabel || placeholder }}
                </span>
            </button>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                align="start"
                :side-offset="6"
                class="z-50 w-64 rounded-lg border bg-popover p-3 text-popover-foreground shadow-md outline-none data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            >
                <div class="mb-2 flex items-center justify-between">
                    <button
                        type="button"
                        class="rounded-md p-1 hover:bg-muted"
                        aria-label="Previous month"
                        @click="shiftMonth(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <span class="text-sm font-medium">{{ monthLabel }}</span>
                    <button
                        type="button"
                        class="rounded-md p-1 hover:bg-muted"
                        aria-label="Next month"
                        @click="shiftMonth(1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-0.5 text-center">
                    <span
                        v-for="w in weekdays"
                        :key="w"
                        class="py-1 text-xs text-muted-foreground"
                        >{{ w }}</span
                    >
                    <template v-for="(day, i) in days" :key="i">
                        <span v-if="!day" />
                        <button
                            v-else
                            type="button"
                            class="flex h-8 items-center justify-center rounded-md text-sm transition-colors hover:bg-accent"
                            :class="[
                                isSameDay(day, selected)
                                    ? 'bg-primary text-primary-foreground hover:bg-primary'
                                    : '',
                                isSameDay(day, today) &&
                                !isSameDay(day, selected)
                                    ? 'ring-1 ring-primary/50'
                                    : '',
                            ]"
                            @click="select(day)"
                        >
                            {{ day.getDate() }}
                        </button>
                    </template>
                </div>

                <div v-if="selected" class="mt-2 flex justify-end">
                    <button
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        @click="clear"
                    >
                        Clear
                    </button>
                </div>
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>

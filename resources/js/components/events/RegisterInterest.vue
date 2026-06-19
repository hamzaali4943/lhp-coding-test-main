<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        eventId: string;
        eventName: string;
        size?: 'sm' | 'default' | 'lg';
        variant?: 'default' | 'secondary' | 'outline';
        block?: boolean;
    }>(),
    { size: 'sm', variant: 'default', block: false },
);

const open = ref(false);

const form = useForm({
    name: '',
    email: '',
    status: 'interested' as 'interested' | 'attending',
});

function submit() {
    form.post(`/events/${props.eventId}/attendees`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                :size="size"
                :variant="variant"
                :class="block ? 'w-full' : ''"
                @click.stop
            >
                Register interest
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md" @click.stop>
            <DialogHeader>
                <DialogTitle>Register for {{ eventName }}</DialogTitle>
                <DialogDescription>
                    Add yourself to the attendee list. We'll email a
                    confirmation and reminders before the event.
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="attendee-name">Name</Label>
                    <Input
                        id="attendee-name"
                        v-model="form.name"
                        required
                        autocomplete="name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="attendee-email">Email</Label>
                    <Input
                        id="attendee-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="attendee-status">I'm…</Label>
                    <select
                        id="attendee-status"
                        v-model="form.status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="interested">Interested</option>
                        <option value="attending">Attending</option>
                    </select>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Adding…' : 'Add me to the list' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

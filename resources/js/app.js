import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Cropper from 'cropperjs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Powers real-time streaming for the AI chat (see App\Livewire\Agents\Chat's
// #[On('echo-private:...')] listener) - window.Echo is where Livewire's own
// Echo integration looks for it.
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

document.addEventListener('alpine:init', () => {
    Alpine.data('postComposer', (initialContent) => ({
        editor: null,

        init() {
            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [StarterKit],
                content: initialContent,
                onUpdate: ({ editor }) => {
                    this.$wire.set('content', editor.getText());
                },
            });
        },

        destroy() {
            this.editor?.destroy();
        },
    }));

    Alpine.data('launchesCalendar', (events) => ({
        calendar: null,

        init() {
            this.calendar = new Calendar(this.$refs.calendar, {
                plugins: [dayGridPlugin, interactionPlugin],
                initialView: 'dayGridMonth',
                events,
                editable: true,
                height: 'auto',

                eventDrop: (info) => {
                    this.$wire.reschedule(info.event.extendedProps.postId, info.event.startStr).then((success) => {
                        if (!success) {
                            info.revert();
                        }
                    });
                },

                dateClick: (info) => {
                    window.location = this.$el.dataset.composeUrl + '?date=' + info.dateStr;
                },

                eventClick: (info) => {
                    window.location = this.$el.dataset.editUrlBase + '/' + info.event.extendedProps.postId + '/compose';
                },
            });

            this.calendar.render();
        },
    }));

    // The plan's reduced-scope substitute for a full Polotno embed: a basic
    // crop/overlay step for image attachments, not a design editor. Works
    // entirely client-side on the raw File the user picked (bypassing
    // Livewire's own upload lifecycle for the crop preview) - the cropped
    // result is synced back as a base64 PNG via $wire.set(), decoded
    // server-side with the same addMediaFromBase64() the AI agent's
    // GenerateImageTool already uses.
    Alpine.data('imageCropper', () => ({
        cropper: null,
        cropSrc: null,

        handleFileSelect(event) {
            const file = event.target.files[0];

            this.cropper?.destroy();
            this.cropper = null;

            if (!file || !file.type.startsWith('image/')) {
                this.cropSrc = null;
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.cropSrc = e.target.result;
                this.$nextTick(() => {
                    this.cropper = new Cropper(this.$refs.cropTarget);
                });
            };
            reader.readAsDataURL(file);
        },

        async applyCrop() {
            const selection = this.cropper?.getCropperSelection();
            if (!selection) return;

            const canvas = await selection.$toCanvas();
            this.$wire.set('croppedImage', canvas.toDataURL('image/png'));
        },

        cancelCrop() {
            this.cropper?.destroy();
            this.cropper = null;
            this.cropSrc = null;
            this.$wire.set('croppedImage', null);
        },

        destroy() {
            this.cropper?.destroy();
        },
    }));
});

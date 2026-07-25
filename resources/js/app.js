import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

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
});

import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const PRIORITY_COLORS = {
    low: 'text-zinc-400',
    medium: 'text-amber-500',
    high: 'text-orange-500',
    urgent: 'text-red-600',
};

function initialsOf(name) {
    const words = (name ?? '').trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '?';
    }

    if (words.length === 1) {
        return words[0].slice(0, 2).toUpperCase();
    }

    return (words[0][0] + words[words.length - 1][0]).toUpperCase();
}

document.addEventListener('alpine:init', () => {
    // A field that displays as plain text/avatar until clicked, then reveals
    // a native <select> to change it — mirrors the task detail page's
    // click-to-edit sidebar fields, but purely client-side (for forms that
    // haven't been saved yet, like Create Task).
    Alpine.data('inlineSelect', (initialValue = '', initialLabel = '') => ({
        editing: false,
        value: initialValue,
        label: initialLabel,
        initialsOf,
        priorityColor: (value) => PRIORITY_COLORS[value] ?? 'text-zinc-400',
        sync(event) {
            this.value = event.target.value;
            this.label = event.target.selectedOptions[0]?.text ?? '';
            this.editing = false;
        },
    }));

    // A field that displays as plain text/avatar until clicked, then opens a
    // floating menu of options (rather than a native <select>) — used for
    // every "pick a user/status/priority/etc." field across Task and Project
    // pages. `choose()` gives instant optimistic feedback on unsaved forms
    // (Create Task/Project); pages editing an already-saved record just wire
    // each option's click straight to a Livewire save method and ignore
    // value/label, since the server round-trip re-renders the real value.
    Alpine.data('dropdownMenu', (initialValue = '', initialLabel = '') => ({
        open: false,
        value: initialValue,
        label: initialLabel,
        initialsOf,
        priorityColor: (value) => PRIORITY_COLORS[value] ?? 'text-zinc-400',
        choose(value, label) {
            this.value = value;
            this.label = label;
            this.open = false;
        },
    }));

    Alpine.data('quillEditor', (initialValue = '') => ({
        quill: null,

        init() {
            this.quill = mountQuill(this.$refs.editor, this.$refs.input, initialValue);
        },
    }));

    // Like quillEditor, but doesn't create the Quill instance until
    // startEditing() is called for the first time. Quill measures text
    // layout on init, so creating it while its container is display:none
    // (e.g. behind an x-show toggle that starts closed) leaves it in a
    // broken state — every keystroke throws "Cannot read properties of
    // null (reading 'offset')" and never reaches the bound hidden input.
    Alpine.data('lazyQuillEditor', (initialValue = '') => ({
        editing: false,
        quill: null,

        startEditing() {
            this.editing = true;

            this.$nextTick(() => {
                if (! this.quill) {
                    this.quill = mountQuill(this.$refs.editor, this.$refs.input, initialValue);
                }
            });
        },
    }));
});

function mountQuill(editorEl, inputEl, initialValue) {
    const quill = new Quill(editorEl, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block', 'link'],
                ['clean'],
            ],
        },
    });

    if (initialValue) {
        quill.clipboard.dangerouslyPasteHTML(initialValue);
    }

    quill.on('text-change', () => {
        const isEmpty = quill.getText().trim().length === 0;
        inputEl.value = isEmpty ? '' : quill.root.innerHTML;
        inputEl.dispatchEvent(new Event('input'));
    });

    return quill;
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

// Live notifications: Reverb pushes each new notification to the signed-in
// user's private channel the moment it's sent; this announces it, and the bell
// component reacts by fetching what's new, flashing the notch and refreshing
// its badge. It (re)checks after every page change, not just on first load:
// the app usually boots on the sign-in page and reaches the dashboard via
// wire:navigate, which doesn't re-run scripts. It subscribes once per user and
// leaves the channel again after logout.
let liveUserId = null;

function listenForLiveNotifications() {
    const userId = document.querySelector('meta[name="user-id"]')?.content || null;

    if (! window.Echo || userId === liveUserId) {
        return;
    }

    if (liveUserId) {
        window.Echo.leave(`App.Models.User.${liveUserId}`);
    }

    liveUserId = userId;

    if (userId) {
        window.Echo.private(`App.Models.User.${userId}`).notification(() => {
            window.dispatchEvent(new CustomEvent('notification-received'));
        });
    }
}

document.addEventListener('livewire:navigated', listenForLiveNotifications);

// After the live connection drops and comes back, check once for anything sent
// while it was down (the push for it was missed); a healthy connection costs nothing.
let liveWasConnected = false;

window.Echo?.connector?.pusher?.connection?.bind('state_change', ({ current }) => {
    if (current !== 'connected') {
        return;
    }

    if (liveWasConnected) {
        window.dispatchEvent(new CustomEvent('notification-received'));
    }

    liveWasConnected = true;
});

// Profile photo cropper: drag to position, zoom (slider, wheel or pinch), then
// the browser crops a 512px square and uploads only that via Livewire, which
// saves it on arrival (the profile component's updatedPhoto()).
document.addEventListener('alpine:init', () => {
    Alpine.data('photoCropper', () => ({
        open: false,
        src: null,
        img: null,
        size: 288,
        base: 1,
        zoom: 1,
        x: 0,
        y: 0,
        pointers: {},
        pinchFrom: null,
        dragging: false,
        saving: false,
        error: '',

        get scale() {
            return this.base * this.zoom;
        },

        pick(event) {
            const file = event.target.files[0];
            event.target.value = '';
            this.error = '';

            if (! file) {
                return;
            }

            if (! file.type.startsWith('image/')) {
                this.error = 'Choose an image file.';

                return;
            }

            this.load(URL.createObjectURL(file));
        },

        load(url) {
            const img = new Image();
            img.onload = () => {
                this.img = img;
                this.src = url;
                this.saving = false;
                this.open = true;
                this.$nextTick(() => this.fit());
            };
            img.onerror = () => {
                this.error = "That image couldn't be opened.";
            };
            img.src = url;
        },

        fit() {
            this.size = this.$refs.frame.clientWidth;
            this.base = Math.max(this.size / this.img.naturalWidth, this.size / this.img.naturalHeight);
            this.zoom = 1;
            this.x = (this.size - this.img.naturalWidth * this.base) / 2;
            this.y = (this.size - this.img.naturalHeight * this.base) / 2;
        },

        clamp() {
            const width = this.img.naturalWidth * this.scale;
            const height = this.img.naturalHeight * this.scale;
            this.x = Math.min(0, Math.max(this.size - width, this.x));
            this.y = Math.min(0, Math.max(this.size - height, this.y));
        },

        // Zoom around a point in the frame (its centre by default), so what's under it stays put.
        setZoom(zoom, cx = this.size / 2, cy = this.size / 2) {
            zoom = Math.min(4, Math.max(1, zoom));
            const ratio = zoom / this.zoom;
            this.x = cx - (cx - this.x) * ratio;
            this.y = cy - (cy - this.y) * ratio;
            this.zoom = zoom;
            this.clamp();
        },

        down(event) {
            this.$refs.frame.setPointerCapture(event.pointerId);
            this.pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
            this.dragging = true;
            this.pinchFrom = null;
        },

        move(event) {
            const previous = this.pointers[event.pointerId];

            if (! previous) {
                return;
            }

            const ids = Object.keys(this.pointers);

            if (ids.length === 1) {
                this.x += event.clientX - previous.x;
                this.y += event.clientY - previous.y;
                this.clamp();
            }

            this.pointers[event.pointerId] = { x: event.clientX, y: event.clientY };

            if (ids.length === 2) {
                const [a, b] = ids.map((id) => this.pointers[id]);
                const distance = Math.hypot(a.x - b.x, a.y - b.y);
                const rect = this.$refs.frame.getBoundingClientRect();

                if (this.pinchFrom) {
                    this.setZoom(this.zoom * (distance / this.pinchFrom), (a.x + b.x) / 2 - rect.left, (a.y + b.y) / 2 - rect.top);
                }

                this.pinchFrom = distance;
            }
        },

        up(event) {
            delete this.pointers[event.pointerId];
            this.pinchFrom = null;
            this.dragging = Object.keys(this.pointers).length > 0;
        },

        wheel(event) {
            const rect = this.$refs.frame.getBoundingClientRect();
            this.setZoom(this.zoom * (1 - event.deltaY * 0.0015), event.clientX - rect.left, event.clientY - rect.top);
        },

        close() {
            this.open = false;
            this.pointers = {};
        },

        save() {
            const output = 512;
            const canvas = document.createElement('canvas');
            canvas.width = output;
            canvas.height = output;

            const source = this.size / this.scale;
            canvas.getContext('2d').drawImage(this.img, -this.x / this.scale, -this.y / this.scale, source, source, 0, 0, output, output);

            this.saving = true;
            canvas.toBlob((blob) => {
                this.$wire.upload(
                    'photo',
                    new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' }),
                    () => this.close(),
                    () => {
                        this.saving = false;
                        this.error = 'Upload failed — please try again.';
                    },
                );
            }, 'image/jpeg', 0.9);
        },
    }));
});

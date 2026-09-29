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

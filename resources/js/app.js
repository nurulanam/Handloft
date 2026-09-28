import Quill from 'quill';
import 'quill/dist/quill.snow.css';

document.addEventListener('alpine:init', () => {
    Alpine.data('quillEditor', (initialValue = '') => ({
        quill: null,

        init() {
            this.quill = new Quill(this.$refs.editor, {
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
                this.quill.clipboard.dangerouslyPasteHTML(initialValue);
            }

            this.quill.on('text-change', () => {
                const isEmpty = this.quill.getText().trim().length === 0;
                this.$refs.input.value = isEmpty ? '' : this.quill.root.innerHTML;
                this.$refs.input.dispatchEvent(new Event('input'));
            });
        },
    }));
});

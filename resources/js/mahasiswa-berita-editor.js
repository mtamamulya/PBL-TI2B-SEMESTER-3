import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const editorElement = document.querySelector('[data-rich-text-editor]');
const contentInput = document.querySelector('[data-rich-text-input]');
const countElement = document.querySelector('[data-content-count]');

if (editorElement && contentInput instanceof HTMLTextAreaElement) {
    const quill = new Quill(editorElement.querySelector('.min-h-64'), {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }, { align: [] }],
                ['blockquote', 'link', 'clean'],
            ],
        },
        placeholder: 'Tuliskan isi berita dengan rapi...',
    });

    if (contentInput.value.trim()) {
        quill.setContents(quill.clipboard.convert({
            html: contentInput.value,
            text: contentInput.value,
        }), 'silent');
    }

    quill.root.setAttribute('aria-labelledby', 'berita-content-label');

    const updateCount = () => {
        const length = quill.getText().trim().length;
        if (countElement) {
            countElement.textContent = new Intl.NumberFormat('id-ID').format(length);
            countElement.classList.toggle('text-[#DC2626]', length > 5000);
            countElement.classList.toggle('font-semibold', length > 5000);
        }
    };

    quill.on('text-change', updateCount);
    updateCount();

    editorElement.closest('form')?.addEventListener('submit', (event) => {
        if (quill.getText().trim().length > 5000) {
            event.preventDefault();
            quill.focus();
            return;
        }

        contentInput.value = quill.getSemanticHTML();
    });
}

/**
 * Upload page: drag-and-drop onto the dropzone, plus a queue preview so the
 * admin can see what is about to be sent (and its size) before submitting.
 *
 * Progressive enhancement only — the plain <input type="file"> works without
 * any of this.
 */

const ACCEPTED = ['epub', 'pdf', 'cbz', 'cbr'];

export function initUploadDropzone() {
    const dropzone = document.getElementById('dropzone');
    const input    = document.getElementById('book-input');
    const queue    = document.getElementById('uploadQueue');
    const form     = document.getElementById('uploadForm');
    const submit   = document.getElementById('uploadSubmit');
    if (!dropzone || !input) return;

    ['dragenter', 'dragover'].forEach((type) => {
        dropzone.addEventListener(type, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach((type) => {
        dropzone.addEventListener(type, (e) => {
            e.preventDefault();
            if (type === 'dragleave' && dropzone.contains(e.relatedTarget)) return;
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (!files || !files.length) return;
        // DataTransfer is the only way to put dropped files into an <input>.
        const dt = new DataTransfer();
        Array.from(files).forEach((f) => {
            if (ACCEPTED.includes(f.name.split('.').pop().toLowerCase())) dt.items.add(f);
        });
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    input.addEventListener('change', () => renderQueue(input.files, queue, submit));

    form?.addEventListener('submit', () => {
        if (!submit) return;
        // Big files take a while and the page is a full POST — say so rather
        // than letting the admin wonder whether the click registered.
        submit.disabled = true;
        submit.textContent = 'Uploading…';
    });
}

function renderQueue(files, queue, submit) {
    if (!queue) return;
    queue.replaceChildren();
    if (!files || !files.length) {
        queue.hidden = true;
        if (submit) submit.textContent = 'Upload selected';
        return;
    }
    let total = 0;
    Array.from(files).forEach((f) => {
        total += f.size;
        const li = document.createElement('li');
        const name = document.createElement('span');
        name.className = 'upload-queue-name';
        name.textContent = f.name;
        const size = document.createElement('span');
        size.className = 'upload-queue-size';
        size.textContent = formatBytes(f.size);
        li.append(name, size);
        queue.appendChild(li);
    });
    queue.hidden = false;
    if (submit) {
        submit.textContent = `Upload ${files.length} file${files.length === 1 ? '' : 's'} (${formatBytes(total)})`;
    }
}

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

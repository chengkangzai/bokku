import FilePondPluginPdfPreview from 'filepond-plugin-pdf-preview';
import 'filepond-plugin-pdf-preview/dist/filepond-plugin-pdf-preview.min.css';

// Wait for FilePond to be available (loaded by Filament)
document.addEventListener('DOMContentLoaded', () => {
    const checkFilePond = setInterval(() => {
        if (window.FilePond) {
            window.FilePond.registerPlugin(FilePondPluginPdfPreview);
            clearInterval(checkFilePond);
        }
    }, 100);
});

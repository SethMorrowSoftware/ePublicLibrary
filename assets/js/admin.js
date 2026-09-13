/**
 * Admin entry point. The admin UI is server-rendered; this only supplies the
 * behaviours that inline handlers used to (and could not, under the CSP),
 * plus the upload dropzone.
 */

import { initBehaviors } from './shared/behaviors.js';
import { initThemeToggle } from './shared/theme.js';
import { initUploadDropzone } from './admin/upload-dropzone.js';

initBehaviors();
initThemeToggle();
initUploadDropzone();

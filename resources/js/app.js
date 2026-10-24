// Bootstrap JS is bundled locally; the customer portal must work before the
// client is authenticated and has internet access.
import 'bootstrap';

// Import the icons CSS through Vite (not Sass) so the font files are emitted
// into public/build/assets instead of being left as unresolved runtime URLs.
import 'bootstrap-icons/font/bootstrap-icons.css';

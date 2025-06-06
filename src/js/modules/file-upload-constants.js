/**
 * File Upload Constants
 * Centralized configuration and constants for file upload functionality
 */

export const FILE_UPLOAD_CONFIG = {
	maxFileSize: 5 * 1024 * 1024, // 5MB
	allowedTypes: ["image/jpeg", "image/jpg", "image/png"],
	allowedExtensions: [".jpg", ".jpeg", ".png"],
	
	selectors: {
		primaryInput: '#realizace-upload',
		fallbackDataName: 'input[data-name="mfile-747"]',
		fallbackClass: '.wpcf7-drag-n-drop-file',
		wrapper: '.codedropz-upload-wrapper',
		handler: '.codedropz-upload-handler',
		inner: '.codedropz-upload-inner'
	},
	
	texts: {
		dragDrop: "Přetáhněte fotky sem",
		or: "nebo", 
		browse: "klikněte pro nahrání"
	},
	
	animation: {
		staggerDelay: 0.075
	}
};

export const FILE_UPLOAD_ICON = `<svg class="file-upload-icon" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
	<path d="M3.55556 32C2.57778 32 1.74104 31.6521 1.04533 30.9564C0.34963 30.2607 0.00118519 29.4234 0 28.4444V3.55556C0 2.57778 0.348445 1.74104 1.04533 1.04533C1.74222 0.34963 2.57896 0.00118519 3.55556 0H28.4444C29.4222 0 30.2596 0.348445 30.9564 1.04533C31.6533 1.74222 32.0012 2.57896 32 3.55556V28.4444C32 29.4222 31.6521 30.2596 30.9564 30.9564C30.2607 31.6533 29.4234 32.0012 28.4444 32H3.55556ZM3.55556 28.4444H28.4444V3.55556H3.55556V28.4444ZM5.33333 24.8889H26.6667L20 16L14.6667 23.1111L10.6667 17.7778L5.33333 24.8889Z" fill="#CCCCCC"/>
</svg>`;
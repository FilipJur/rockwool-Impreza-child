/**
 * File Upload Constants
 * Centralized configuration and constants for file upload functionality
 */

export const FILE_UPLOAD_CONFIG = {
	maxFileSize: 5 * 1024 * 1024, // 5MB
	allowedTypes: ["image/jpeg", "image/jpg", "image/png"],
	allowedExtensions: [".jpg", ".jpeg", ".png"],
	
	selectors: {
		primaryInput: '#realize-upload', // Fixed: was 'realizace-upload' but actual ID is 'realize-upload'
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
	},
	
	iconPath: '/wp-content/themes/Impreza-child/src/images/icons/file.svg'
};
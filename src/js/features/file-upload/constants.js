/**
 * File Upload Constants
 * Centralized configuration and constants for file upload functionality
 */

export const FILE_UPLOAD_CONFIG = {
	selectors: {
		primaryInput: "#realizace-upload", // Fixed: corrected to match actual ID
		fallbackDataName: 'input[data-name="mfile-747"]',
		fallbackClass: ".wpcf7-drag-n-drop-file",
		wrapper: ".codedropz-upload-wrapper",
		handler: ".codedropz-upload-handler",
		inner: ".codedropz-upload-inner",
	},

	texts: {
		dragDrop: "Přetáhněte fotky sem nebo",
		or: "",
		browse: "klikněte pro nahrání",
	},

	animation: {
		staggerDelay: 0.05,
	},

	iconPath: "/wp-content/themes/Impreza-child/src/images/icons/file.svg",
};

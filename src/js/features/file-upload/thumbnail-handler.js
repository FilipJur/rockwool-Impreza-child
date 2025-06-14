/**
 * Thumbnail Handler Module
 * Handles thumbnail creation and management for file previews
 */

const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];

/**
 * Enhance thumbnail display
 * @param {HTMLElement} item 
 */
export function enhanceThumbnail(item) {
	const imageContainer = item.querySelector('.dnd-upload-image');
	if (!imageContainer) return;

	const existingImg = imageContainer.querySelector('img');
	if (existingImg && existingImg.src && existingImg.src !== '') {
		console.log("[ThumbnailHandler] Thumbnail found:", existingImg.src);
		existingImg.style.display = 'block';
		existingImg.style.zIndex = '2';
		return;
	}

	const fileName = getFileName(item);
	if (fileName && isImageFile(fileName)) {
		console.log("[ThumbnailHandler] Creating real thumbnail for image:", fileName);
		createImageThumbnail(item, imageContainer, fileName);
	} else {
		console.log("[ThumbnailHandler] Non-image file, using fallback icon for:", fileName || 'unknown file');
	}
}

/**
 * Get filename from preview item
 * @param {HTMLElement} item 
 * @returns {string|null}
 */
function getFileName(item) {
	const nameSpan = item.querySelector('.name span:first-child');
	return nameSpan ? nameSpan.textContent.trim() : null;
}

/**
 * Check if file is an image
 * @param {string} fileName 
 * @returns {boolean}
 */
function isImageFile(fileName) {
	const extension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
	return imageExtensions.includes(extension);
}

/**
 * Create real image thumbnail from file data
 * @param {HTMLElement} item 
 * @param {HTMLElement} imageContainer 
 * @param {string} fileName 
 */
function createImageThumbnail(item, imageContainer, fileName) {
	const fileInput = document.querySelector('#realize-upload, input[data-name="mfile-747"], .wpcf7-drag-n-drop-file');
	if (!fileInput || !fileInput.files) {
		console.log("[ThumbnailHandler] No file input or files found");
		return;
	}

	const matchingFile = Array.from(fileInput.files).find(file => file.name === fileName);
	if (!matchingFile) {
		console.log("[ThumbnailHandler] No matching file found for:", fileName);
		return;
	}

	console.log("[ThumbnailHandler] Found matching file:", matchingFile);

	const reader = new FileReader();
	reader.onload = (e) => {
		console.log("[ThumbnailHandler] File read successfully, creating thumbnail");

		const img = document.createElement('img');
		img.src = e.target.result;
		Object.assign(img.style, {
			width: '100%',
			height: '100%',
			objectFit: 'cover',
			borderRadius: '5px',
			position: 'relative',
			zIndex: '2',
			display: 'block'
		});

		imageContainer.appendChild(img);
		console.log("[ThumbnailHandler] Real thumbnail created successfully");
	};

	reader.onerror = () => {
		console.log("[ThumbnailHandler] Error reading file for thumbnail");
	};

	reader.readAsDataURL(matchingFile);
}
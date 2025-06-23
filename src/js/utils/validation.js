/**
 * Form validation utilities
 * Czech-specific validation rules
 */

export const validation = {
  /**
   * Validate Czech IČO (company registration number)
   * @param {string} ico - IČO to validate
   * @returns {Object} Validation result
   */
  ico(ico) {
    const cleaned = ico.replace(/\s/g, '');

    if (!/^\d{8}$/.test(cleaned)) {
      return {
        valid: false,
        error: 'I mistr se někdy utne. Zadané IČO je neplatné.'
      };
    }

    // Czech IČO checksum validation
    const digits = cleaned.split('').map(Number);
    const weights = [8, 7, 6, 5, 4, 3, 2];
    const sum = digits.slice(0, 7).reduce((acc, digit, index) => acc + digit * weights[index], 0);
    const remainder = sum % 11;
    const checksum = remainder === 0 ? 1 : (remainder === 1 ? 0 : 11 - remainder);

    if (digits[7] !== checksum) {
      return {
        valid: false,
        error: 'I mistr se někdy utne. Zadané IČO je neplatné.'
      };
    }

    return { valid: true };
  },

  /**
   * Validate email address
   * @param {string} email - Email to validate
   * @returns {Object} Validation result
   */
  email(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRegex.test(email)) {
      return {
        valid: false,
        error: 'Neplatný formát e-mailové adresy'
      };
    }

    return { valid: true };
  },

  /**
   * Validate Czech phone number
   * @param {string} phone - Phone to validate
   * @returns {Object} Validation result
   */
  phone(phone) {
    const cleaned = phone.replace(/[\s\-\(\)]/g, '');
    const phoneRegex = /^(\+420)?[0-9]{9}$/;

    if (!phoneRegex.test(cleaned)) {
      return {
        valid: false,
        error: 'Neplatný formát telefonního čísla'
      };
    }

    return { valid: true };
  },

  /**
   * Validate required field
   * @param {string} value - Value to validate
   * @param {string} fieldName - Field name for error message
   * @returns {Object} Validation result
   */
  required(value, fieldName = 'Pole') {
    if (!value || value.trim() === '') {
      return {
        valid: false,
        error: `${fieldName} je povinné`
      };
    }

    return { valid: true };
  },

  /**
   * Validate file type
   * @param {File} file - File to validate
   * @param {Array} allowedTypes - Allowed MIME types
   * @returns {Object} Validation result
   */
  fileType(file, allowedTypes = ['image/jpeg', 'image/jpg', 'image/png']) {
    if (!allowedTypes.includes(file.type)) {
      const allowedExtensions = allowedTypes.map(type => {
        switch (type) {
          case 'image/jpeg': return 'JPG';
          case 'image/jpg': return 'JPG';
          case 'image/png': return 'PNG';
          default: return type;
        }
      });

      return {
        valid: false,
        error: `Pouze soubory typu ${allowedExtensions.join(', ')} jsou povoleny`
      };
    }

    return { valid: true };
  },

  /**
   * Validate file size
   * @param {File} file - File to validate
   * @param {number} maxSize - Maximum size in bytes
   * @returns {Object} Validation result
   */
  fileSize(file, maxSize = 5 * 1024 * 1024) { // 5MB default
    if (file.size > maxSize) {
      const maxSizeMB = Math.round(maxSize / (1024 * 1024));
      return {
        valid: false,
        error: `Soubor je příliš velký (maximum ${maxSizeMB}MB)`
      };
    }

    return { valid: true };
  },

  /**
   * Format file size for display
   * @param {number} bytes - File size in bytes
   * @returns {string} Formatted size string
   */
  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  },

  /**
   * Validate multiple rules for a single value
   * @param {any} value - Value to validate
   * @param {Array} rules - Array of validation rules
   * @returns {Object} Validation result
   */
  validate(value, rules) {
    for (const rule of rules) {
      const result = rule(value);
      if (!result.valid) {
        return result;
      }
    }

    return { valid: true };
  }
};

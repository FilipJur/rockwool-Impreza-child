# Tailwind CSS - Clean Separation Setup ✅

## Perfect Solution: Separated Concerns

### 📁 **File Structure**
```
├── style.css           # WordPress theme styles (your custom SCSS)
├── tailwind.css        # Tailwind utilities only
├── src/
│   ├── tailwind.css    # Source: @tailwind utilities;
│   └── scss/main.scss  # Source: Your custom SCSS
└── tailwind.config.js  # Minimal Tailwind config
```

### 🔄 **Build Process**
```bash
# Two-step SCSS compilation with PostCSS processing:
sass src/scss/main.scss:build/css/style-temp.css  # SCSS → temp file
postcss build/css/style-temp.css -o style.css     # PostCSS → final style.css
postcss src/tailwind.css -o tailwind.css          # Tailwind utilities → tailwind.css
```

### 📋 **WordPress Enqueue Order**
```php
1. impreza-style        (parent theme)
2. tailwind-utilities   (Tailwind CSS utilities)
3. impreza-child-style  (your custom styles - highest priority)
```

### ✅ **Benefits**
- **WordPress Convention**: `style.css` contains proper theme header
- **Clean Separation**: Utilities vs custom styles in separate files
- **PostCSS Processing**: SCSS gets autoprefixer and other PostCSS benefits via temp file
- **Proper Priority**: Your custom styles override Tailwind utilities
- **No Conflicts**: Tailwind preflight disabled, won't break parent theme
- **Easy Debugging**: Can disable Tailwind without affecting custom styles

### 🎯 **Usage**
```html
<!-- Use Tailwind utilities in templates -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4">
  <div class="bg-white rounded-lg shadow-md p-6">
    <!-- Your custom CSS classes still work -->
    <div class="my-custom-component">
      <!-- Tailwind utilities for quick styling -->
      <h3 class="text-lg font-semibold mb-2">Title</h3>
      <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
        Button
      </button>
    </div>
  </div>
</div>
```

### 🛠 **Commands**
```bash
npm run build:css      # Build both files
npm run watch:css      # Watch both files  
npm run build:css:prod # Production build
npm run clean          # Clean generated files
```

### 🎨 **Best Practices**
1. **Tailwind for layout/utilities**: `grid`, `flex`, `p-4`, `text-lg`
2. **Custom SCSS for components**: Complex components, brand-specific styles
3. **Override when needed**: Your `style.css` loads last, overrides Tailwind
4. **Keep it clean**: No custom Tailwind config unless absolutely needed

This setup gives you the best of both worlds - Tailwind's utility power with full control over your custom styles!
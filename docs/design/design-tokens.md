**# Design Tokens

## Colors

```css
/* Brand Colors */
--color-primary: #DB0626;        /* ROCKWOOL Red - CTAs, accents */
--color-primary-dark: #D20014;   /* Icons, secondary accents */

/* Neutrals */
--color-black: #000000;          /* Main text */
--color-white: #FFFFFF;          /* Backgrounds, button text */
--color-gray-dark: #2B2B2B;      /* Footer background */
--color-gray-medium: #666666;    /* Placeholders, labels */
--color-gray-light: #999999;     /* Footer text, disabled */
--color-gray-border: #CCCCCC;    /* Borders, dividers */
--color-gray-bg: #F2F2F2;        /* Card backgrounds */

/* Accent */
--color-accent: #33A7CC;         /* Upload text, links */
```

## Typography

```css
/* Font Families */
--font-primary: 'Avenir Next', sans-serif;
--font-secondary: 'General Sans', sans-serif;

/* Headings */
--text-hero: 700 48px/1.366em var(--font-primary);     /* Hero title, UPPERCASE */
--text-h1: 600 30px/1.366em var(--font-primary);       /* Section titles */
--text-h2: 700 24px/1.366em var(--font-primary);       /* Subsection titles */
--text-h3: 600 20px/1.366em var(--font-primary);       /* Card titles */

/* Body Text */
--text-body-bold: 700 18px/1.366em var(--font-primary);
--text-body: 400 18px/1.366em var(--font-primary);
--text-body-center: 400 18px/1.366em var(--font-primary); /* text-align: center */

/* Interface */
--text-button-lg: 600 18px/1.5em var(--font-primary);   /* Primary buttons */
--text-button-md: 600 16px/1.5em var(--font-primary);   /* Secondary buttons */
--text-button-sm: 600 12px/1.5em var(--font-primary);   /* Small buttons */
--text-input: 500 16px/1.5em var(--font-primary);       /* Form inputs */
--text-label: 400 16px/1.5em var(--font-primary);       /* Form labels */

/* Small Text */
--text-caption: 400 14px/1.366em var(--font-primary);   /* Subtitles */
--text-fine: 400 14px/1.5em var(--font-primary);        /* Legal text */
--text-micro: 400 12px/1.366em var(--font-primary);     /* Field notes */
--text-upload: 500 16px/1.35em var(--font-secondary);   /* Upload area */
```

## Layout

```css
/* Container Widths */
--container-page: 1440px;
--container-content: 900px;
--container-form: 440px;
--container-hero: 619px;

/* Spacing Scale */
--space-xs: 5px;
--space-sm: 10px;
--space-md: 20px;
--space-lg: 30px;
--space-xl: 40px;
--space-2xl: 60px;
--space-3xl: 80px;

/* Component Spacing */
--gap-form: 30px;           /* Between form sections */
--gap-form-field: 20px;     /* Between form fields */
--gap-benefits: 20px;       /* Between benefit cards */
--gap-hero: 40px;           /* Hero content vertical */
--gap-content: 80px;        /* Between major sections */
```

## Components

### Buttons
```css
/* Primary Button */
.btn-primary {
  background: var(--color-primary);
  color: var(--color-white);
  font: var(--text-button-lg);
  padding: 20px 30px;
  height: 67px;
  gap: 15px;
}

/* Secondary Button */
.btn-secondary {
  background: var(--color-primary);
  color: var(--color-white);
  font: var(--text-button-md);
  padding: 15px 20px;
  gap: 15px;
}

/* Form Submit Button */
.btn-submit {
  background: var(--color-primary);
  color: var(--color-white);
  font: var(--text-button-md);
  padding: 0 30px;
  height: 60px;
  width: 100%;
  gap: 10px;
}
```

### Form Elements
```css
/* Input Field */
.input {
  background: var(--color-white);
  border-bottom: 1px solid var(--color-gray-border);
  padding: 10px 0;
  font: var(--text-input);
  color: var(--color-black);
}

.input:focus {
  border-bottom-color: var(--color-primary);
}

/* Checkbox */
.checkbox {
  border: 1px solid var(--color-gray-border);
  border-radius: 3px;
  background: var(--color-white);
}

.checkbox:checked {
  background: var(--color-primary);
}

/* Upload Area */
.upload-area {
  background: var(--color-white);
  border: 1px dashed var(--color-gray-border);
  border-radius: 6px;
  padding: 30px 0;
  text-align: center;
  font: var(--text-upload);
  color: var(--color-accent);
}
```

### Cards
```css
/* Benefit Card */
.card-benefit {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  flex: 1;
}

/* Expert Tip Card */
.card-tip {
  background: var(--color-gray-bg);
  width: 900px;
  padding: 60px 60px 60px 400px;
  gap: 10px;
  position: relative;
}

/* CTA Card */
.card-cta {
  background: var(--color-primary);
  width: 900px;
  padding: 50px 400px 50px 50px;
  gap: 20px;
}
```

## Image References

```css
/* Figma Image IDs */
--img-hero-bg: fa7553ea40b845a1e59835017126b0be7fe092e8;
--img-expert: a63c572b58792ad590f88ba27e9a2534795c856f;
--img-multitool: 6c2d56206ebf2a8ea72943ed3fbe311723fce2f7;
--img-side: 12b70d142d27912a8ba9e022c837947d6faecb74;
```

## Responsive Breakpoints

```css
/* Breakpoints */
--bp-mobile: 320px;
--bp-tablet: 768px;
--bp-desktop: 1440px;
```

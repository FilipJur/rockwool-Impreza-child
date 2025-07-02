# GitHub Actions CI/CD Deployment Setup

This document explains how to set up automatic deployment from GitHub to your staging server using FTP.

## Overview

The CI/CD pipeline automatically:
1. **Builds your theme** when you push to the `main` branch
2. **Deploys to staging server** via FTP
3. **Takes 2-3 minutes** from push to live staging site

## Required GitHub Secrets

You need to add these secrets to your GitHub repository for the deployment to work.

### 1. Navigate to Repository Secrets

1. Go to your GitHub repository
2. Click **Settings** (top menu)
3. Click **Secrets and variables** → **Actions** (left sidebar)
4. Click **New repository secret**

### 2. Add Required Secrets

Add each of these secrets one by one:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `STAGING_FTP_HOST` | Your FTP server hostname | `ftp.yourserver.com` |
| `STAGING_FTP_USER` | Your FTP username | `staging_user` |
| `STAGING_FTP_PASSWORD` | Your FTP password | `your_secure_password` |
| `STAGING_FTP_PATH` | Path to theme directory on server | `/public_html/wp-content/themes/Impreza-child/` |

### 3. Getting FTP Credentials

**From Your Hosting Provider:**

1. **Login to your hosting control panel** (cPanel, Plesk, etc.)
2. **Create FTP account** or use existing one
3. **Set permissions** to allow access to WordPress directory
4. **Note down**: hostname, username, password, and path

**Common hosting paths:**
- **Shared hosting**: `/public_html/wp-content/themes/Impreza-child/`
- **VPS/Dedicated**: `/var/www/html/wp-content/themes/Impreza-child/`
- **Subdomain staging**: `/staging/wp-content/themes/Impreza-child/`

## How the Deployment Works

### Build Process

The GitHub Action runs these steps:

```bash
1. Setup Node.js 22 + PHP 8.4 (matching your local environment)
2. composer install --no-dev --optimize-autoloader
3. npm ci
4. npm run build (production build)
5. Verify all required files exist
6. Create deployment package
7. Upload via FTP
```

### What Gets Deployed

**✅ Included in deployment:**
```
├── style.css (compiled SCSS + PostCSS)
├── tailwind.css (Tailwind utilities)
├── build/js/main.js + main.asset.php
├── build/js/admin.js + admin.asset.php
├── functions.php
├── lib/ (Composer autoloader + dependencies)
├── src/App/ (PHP application code)
├── templates/ (admin card templates)
├── includes/ (additional PHP files)
└── acf-json/ (ACF field definitions)
```

**❌ Excluded from deployment:**
```
├── src/js/, src/scss/ (source files)
├── node_modules/ (NPM dependencies)
├── build/css/ (intermediate build files)
├── package*.json (build configurations)
├── *.map files (source maps)
└── .git/ (version control)
```

## Triggering Deployments

### Automatic Deployment
- **Push to main branch** → automatic deployment
- **Example**: `git push origin main`

### Manual Deployment
1. Go to your GitHub repository
2. Click **Actions** tab
3. Click **Deploy to Staging** workflow
4. Click **Run workflow** button
5. Click **Run workflow** to confirm

## Monitoring Deployments

### View Deployment Status

1. Go to **Actions** tab in your GitHub repository
2. Click on latest workflow run
3. Monitor the progress in real-time

### Deployment Logs

The workflow provides detailed logs for each step:
- ✅ Build verification
- 📦 Package creation
- 🚀 FTP upload progress
- 📊 Build summary

## Troubleshooting

### Common Issues

**❌ FTP Connection Failed**
- Check `STAGING_FTP_HOST` is correct
- Verify FTP user has proper permissions
- Test FTP connection manually with FileZilla

**❌ Build Failed**
- Check if `npm run build` works locally
- Verify all dependencies are in package.json
- Check Node.js/PHP version compatibility

**❌ Missing Files**
- Build verification will catch missing assets
- Check if all required files are being generated

**❌ Wrong File Paths**
- Verify `STAGING_FTP_PATH` ends with `/`
- Check if path exists on your server
- Ensure path includes `/wp-content/themes/Impreza-child/`

### Testing Your Setup

**Before first deployment:**

1. **Test FTP connection locally:**
   ```bash
   # Use FileZilla or command line
   ftp ftp.yourserver.com
   # Login with your credentials
   # Navigate to the theme directory
   ```

2. **Test build process locally:**
   ```bash
   npm run clean
   npm run build
   npm run verify-build
   ```

3. **Push a small change** to test the full pipeline

## Security Best Practices

### FTP Account Security
- ✅ Create dedicated FTP user for CI/CD
- ✅ Limit permissions to theme directory only
- ✅ Use strong password
- ✅ Rotate credentials regularly

### GitHub Secrets Security
- ✅ Never commit secrets to code
- ✅ Use "Protected" branch settings
- ✅ Enable "Masked" flag for sensitive values
- ✅ Separate staging and production credentials

## Advanced Configuration

### Environment-Specific Deployments

For production deployment, add these additional secrets:
```
PROD_FTP_HOST
PROD_FTP_USER  
PROD_FTP_PASSWORD
PROD_FTP_PATH
```

Then create `.github/workflows/deploy-production.yml` with production-specific settings.

### Custom Build Steps

To add custom build steps, modify `.github/workflows/deploy-staging.yml`:

```yaml
- name: Custom build step
  run: |
    # Your custom commands here
    npm run your-custom-script
```

## Support

### Getting Help

1. **Check deployment logs** in GitHub Actions
2. **Test locally** with `npm run build`
3. **Verify FTP credentials** with FTP client
4. **Check hosting provider** documentation

### WordPress-Specific Notes

- **Parent theme**: Ensure Impreza parent theme is installed on staging
- **Plugins**: Install required plugins (WooCommerce, Contact Form 7, myCred, ACF)
- **Database**: Staging should have copy of production database
- **Permissions**: WordPress files should have 644, directories 755

---

**🚀 Ready to deploy!** Push to main branch and watch your code automatically deploy to staging.
# Deploy to Hostinger - Step by Step

## Understanding Your Setup

**You have:**
- 3 WordPress sites on Hostinger shared hosting
- Sites getting hacked with random malicious files
- Need a "set and forget" security solution

**This plugin provides:**
- ✅ Real-time malware scanning
- ✅ Automatic file quarantine
- ✅ Hourly cleanup cron job
- ✅ WordPress core file repair
- ✅ Admin dashboard showing status

---

## Important: Dev Dependencies vs Production

**What you see on your dev machine:**
```bash
composer install
# Installs 184 packages (~104MB)
# Includes: Codeception, PHPUnit, GrumPHP, Symfony, Guzzle, etc.
# These are for TESTING and CODE QUALITY
```

**What gets deployed to Hostinger:**
```bash
composer install --no-dev
# Installs only 3 packages (<100KB)
# - micropackage/requirements
# - micropackage/internationalization  
# - symfony/polyfill-mbstring
```

**The 104MB dev dependencies stay on your local machine!**

---

## Deployment Steps

### Option 1: Direct Upload (Recommended for Hostinger)

**Step 1: Prepare the plugin**
```bash
cd /path/to/obfuscated-malware-scanner

# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Verify what got installed
ls -lh vendor/  # Should be small (<1MB)
```

**Step 2: Create deployment zip**
```bash
# Exclude development files
zip -r oms-plugin.zip . \
  -x "*.git*" \
  -x "*tests/*" \
  -x "*review/*" \
  -x "*.md" \
  -x "phpunit.xml*" \
  -x "phpcs.xml*" \
  -x "codeception.yml" \
  -x "grumphp.yml" \
  -x "composer.json" \
  -x "composer.lock"

# Check the zip size
ls -lh oms-plugin.zip
# Should be small (plugin code + minimal vendor/)
```

**Step 3: Upload to each Hostinger site**
1. Log into Hostinger File Manager
2. Navigate to `public_html/wp-content/plugins/`
3. Upload `oms-plugin.zip`
4. Extract the zip
5. Rename folder to `obfuscated-malware-scanner`
6. Delete the zip file

**Step 4: Activate**
1. Log into WordPress admin
2. Go to Plugins
3. Find "Obfuscated Malware Scanner"
4. Click "Activate"

**Step 5: Verify it's working**
1. Go to Settings → Malware Scanner
2. Check the dashboard shows scan status
3. Check log file exists: `wp-content/oms-logs/malware-scanner.log`
4. Verify cron is scheduled:
   ```bash
   wp cron event list  # Should show oms_daily_cleanup
   ```

---

### Option 2: Git Deploy

**If you have SSH access to Hostinger:**

```bash
# On your Hostinger server
cd public_html/wp-content/plugins/
git clone <your-repo> obfuscated-malware-scanner
cd obfuscated-malware-scanner

# Install production dependencies
composer install --no-dev --optimize-autoloader

# Activate via WP-CLI
wp plugin activate obfuscated-malware-scanner
```

---

## What Runs on Your Hostinger Sites

### File Size Breakdown

```
obfuscated-malware-scanner/
├── includes/           (~50KB - your plugin code)
├── admin/              (~10KB - admin UI)
├── vendor/             (<100KB - 3 tiny packages)
│   ├── micropackage/
│   └── symfony/polyfill-mbstring/
└── obfuscated-malware-scanner.php

Total: ~200KB
```

**No Symfony framework, no Guzzle HTTP client, no Monolog, no PSR bloat!**

### What the Plugin Uses

**For HTTP requests:**
```php
wp_remote_get()    // WordPress native, not Guzzle
wp_remote_post()   // WordPress native
```

**For filesystem:**
```php
WP_Filesystem()    // WordPress native, not Flysystem/Symfony
```

**For caching:**
```php
get_transient()    // WordPress native, not Symfony Cache
set_transient()
```

**For logging:**
```php
error_log()        // PHP native, not Monolog
OMS_Logger         // Custom lightweight logger
```

**Pure WordPress plugin!** 🎉

---

## Monitoring

### Check Plugin is Working

**Via WordPress Admin:**
- Settings → Malware Scanner
- Shows: Last scan time, files scanned, issues found

**Via File Manager:**
```
wp-content/oms-logs/malware-scanner.log
# Contains all scan activity
```

**Via Cron:**
```bash
# If you have SSH/WP-CLI
wp cron event list
# Should show: oms_daily_cleanup (runs daily)
```

### What to Expect

**First Activation:**
- Plugin creates directories: `wp-content/oms-logs/`, `wp-content/oms-quarantine/`
- Schedules daily cron job
- Runs initial full scan
- May quarantine suspicious files

**Daily Operation:**
- Scans hourly for new threats
- Quarantines suspicious files immediately
- Verifies WordPress core files
- Repairs/replaces tampered files
- Logs everything

---

## Troubleshooting

### "Plugin causes white screen"
**Cause:** Critical bugs not yet fixed (see CRITICAL_FIXES_NEEDED.md)
**Solution:** Wait for bug-fix PR before deploying to production

### "Vendor directory is huge (100MB+)"
**Cause:** You ran `composer install` instead of `composer install --no-dev`
**Solution:** Delete vendor/, run with `--no-dev` flag

### "Plugin deactivated itself"
**Cause:** PHP version < 8.1
**Solution:** Update Hostinger PHP version to 8.1+ in hosting panel

### "Can't find micropackage/requirements"
**Cause:** Didn't run composer install
**Solution:** Run `composer install --no-dev` before uploading

---

## For Your 3 Sites

**Best Practice:**

1. **Test on one site first:**
   - Deploy to site #1
   - Monitor for 1 week
   - Check logs daily

2. **If working well:**
   - Deploy to sites #2 and #3
   - Set calendar reminder to check monthly

3. **Set and forget:**
   - Plugin runs automatically
   - Check admin dashboard occasionally
   - Review quarantine directory if issues detected

---

## Size Comparison

| What | Dev Machine | Hostinger Production |
|------|-------------|---------------------|
| **Packages** | 184 packages | 3 packages |
| **Vendor size** | ~104MB | <100KB |
| **Total plugin** | ~110MB | ~200KB |
| **Who needs it** | You (for testing) | Your sites (for security) |

**Bottom line:** The plugin is tiny on your Hostinger sites! 🚀

---

## Next Steps

1. **Wait for bug fixes** (CRITICAL_FIXES_NEEDED.md must be addressed first)
2. **Test locally** with `composer install --no-dev`
3. **Deploy to Hostinger site #1**
4. **Monitor for 1 week**
5. **Deploy to remaining sites**
6. **Enjoy hack-free WordPress!** ✨
